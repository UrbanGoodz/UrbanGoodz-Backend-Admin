<?php

namespace App\Services\UrbanGoodz;

/**
 * Tracks the people a conversation is about, and who "he", "she" or "they"
 * refers to across turns.
 *
 * Shared by both Digital Humans: Skylar (customer) and Monique (operations)
 * run on the same conversation context, so neither has to re-ask who a pronoun
 * refers to once the conversation has established it.
 *
 * The rule that shapes this class: pronouns are NEVER inferred from a person's
 * name, appearance, photograph, voice, or anyone's perception of them. They are
 * only recorded when they come from an authoritative source:
 *
 *   - PRONOUN_SOURCE_PROFILE   the person's own account record
 *   - PRONOUN_SOURCE_DECLARED  someone stated them outright
 *                              ("Marcus uses he/him")
 *   - PRONOUN_SOURCE_USAGE     the speaker referred to that person with a
 *                              pronoun ("Marcus is my driver. He is picking up
 *                              the order.") - the speaker is telling us
 *
 * Until one of those exists, the person's pronouns are unknown and they/them is
 * used. Getting this wrong misgenders a real person, which a neutral default
 * never does, so an unknown here is treated as a fact to respect rather than a
 * gap to fill by guessing.
 */
class PersonReferenceResolver
{
    public const PRONOUN_SOURCE_PROFILE = 'profile';
    public const PRONOUN_SOURCE_DECLARED = 'declared';
    public const PRONOUN_SOURCE_USAGE = 'conversational_usage';
    public const PRONOUN_SOURCE_UNKNOWN = 'unknown';

    /**
     * Pronoun sets, keyed by the subject form.
     *
     * `distinct` marks sets that can disambiguate between two candidates: if a
     * turn contains both "she" and "him", those are different sets and so must
     * point at different people.
     */
    private const PRONOUN_SETS = [
        'he' => ['subject' => 'he', 'object' => 'him', 'possessive' => 'his', 'reflexive' => 'himself'],
        'she' => ['subject' => 'she', 'object' => 'her', 'possessive' => 'her', 'reflexive' => 'herself'],
        'they' => ['subject' => 'they', 'object' => 'them', 'possessive' => 'their', 'reflexive' => 'themselves'],
    ];

    /** Maps any surface form back to its set key. */
    private const PRONOUN_FORMS = [
        'he' => 'he', 'him' => 'he', 'his' => 'he', 'himself' => 'he',
        'she' => 'she', 'her' => 'she', 'hers' => 'she', 'herself' => 'she',
        'they' => 'they', 'them' => 'they', 'their' => 'they', 'theirs' => 'they', 'themselves' => 'they',
    ];

    /** Roles that name a person without naming them, e.g. "the driver". */
    private const ROLE_WORDS = [
        'driver', 'courier', 'customer', 'vendor', 'merchant', 'store manager',
        'manager', 'dispatcher', 'shopper', 'client', 'rider',
    ];

    /** @var array<string, array<string,mixed>> keyed by lowercase entity id */
    private array $people = [];

    private int $turn = 0;

    /**
     * Registers a person from an authoritative record.
     *
     * Pronouns are only stored when the record actually carries them; a record
     * without them leaves the person's pronouns unknown rather than assigning a
     * default that could be wrong.
     */
    public function registerFromRecord(
        string $id,
        string $displayName,
        ?string $relationship = null,
        ?string $pronounSubject = null
    ): void {
        $key = $this->key($displayName);
        $this->people[$key] = [
            'id' => $id,
            'display_name' => $displayName,
            'relationship' => $relationship,
            'pronouns' => null,
            'pronoun_source' => self::PRONOUN_SOURCE_UNKNOWN,
            'last_mentioned_turn' => $this->people[$key]['last_mentioned_turn'] ?? 0,
            'mention_count' => $this->people[$key]['mention_count'] ?? 0,
        ];

        if ($pronounSubject !== null) {
            $this->setPronouns($displayName, $pronounSubject, self::PRONOUN_SOURCE_PROFILE);
        }
    }

    /**
     * Records pronouns for a person.
     *
     * A stronger source overwrites a weaker one, so a profile record or an
     * outright statement always beats an inference drawn from usage, and a
     * correction ("actually Alex uses they/them") is respected.
     */
    public function setPronouns(string $name, string $subjectForm, string $source): void
    {
        $setKey = self::PRONOUN_FORMS[strtolower(trim($subjectForm))] ?? null;
        if ($setKey === null) {
            return;
        }

        $key = $this->key($name);
        if (!isset($this->people[$key])) {
            $this->addPerson($name);
        }

        $existing = $this->people[$key]['pronoun_source'] ?? self::PRONOUN_SOURCE_UNKNOWN;
        if (!$this->sourceOutranks($source, $existing)) {
            return;
        }

        $this->people[$key]['pronouns'] = self::PRONOUN_SETS[$setKey];
        $this->people[$key]['pronoun_source'] = $source;
    }

    /**
     * Reads one conversational turn: learns who is being talked about, records
     * any pronouns the speaker uses for them, and returns what each pronoun in
     * the turn resolved to.
     *
     * @return array<string, string|null> pronoun surface form => display name or null
     */
    public function observeTurn(string $text): array
    {
        $this->turn++;

        // Explicit declarations first - they are the strongest signal in a turn
        // and must not be overridden by usage later in the same sentence.
        $this->captureDeclarations($text);

        $mentioned = $this->captureNames($text);
        return $this->resolvePronounsIn($text, $mentioned);
    }

    /**
     * The pronouns to use for a person.
     *
     * Returns the they/them set when unknown. This is a deliberate default, not
     * a guess about the person.
     *
     * @return array<string,string>
     */
    public function pronounsFor(string $name): array
    {
        $key = $this->key($name);
        return $this->people[$key]['pronouns'] ?? self::PRONOUN_SETS['they'];
    }

    public function pronounsAreKnown(string $name): bool
    {
        $key = $this->key($name);
        return ($this->people[$key]['pronoun_source'] ?? self::PRONOUN_SOURCE_UNKNOWN)
            !== self::PRONOUN_SOURCE_UNKNOWN;
    }

    public function pronounSource(string $name): string
    {
        $key = $this->key($name);
        return $this->people[$key]['pronoun_source'] ?? self::PRONOUN_SOURCE_UNKNOWN;
    }

    /**
     * Resolves a single pronoun against what the conversation has established.
     *
     * Returns null when it is genuinely ambiguous - two equally plausible
     * candidates and nothing to separate them. The caller must ask rather than
     * pick one.
     */
    public function resolve(string $pronoun): ?string
    {
        $setKey = self::PRONOUN_FORMS[strtolower(trim($pronoun))] ?? null;
        if ($setKey === null) {
            return null;
        }

        $candidates = $this->candidatesFor($setKey);
        if (count($candidates) === 1) {
            return $candidates[0]['display_name'];
        }
        if (count($candidates) > 1) {
            // Most recently mentioned wins only if it is strictly more recent.
            usort($candidates, fn ($a, $b) => $b['last_mentioned_turn'] <=> $a['last_mentioned_turn']);
            if ($candidates[0]['last_mentioned_turn'] > $candidates[1]['last_mentioned_turn']) {
                return $candidates[0]['display_name'];
            }
            return null; // genuinely ambiguous
        }

        return null;
    }

    /**
     * Everything the conversation knows about the people in it, for grounding
     * the model.
     *
     * @return array<int, array<string,mixed>>
     */
    public function context(): array
    {
        $out = [];
        foreach ($this->people as $p) {
            $out[] = [
                'name' => $p['display_name'],
                'relationship' => $p['relationship'],
                'pronouns' => $p['pronouns']
                    ? "{$p['pronouns']['subject']}/{$p['pronouns']['object']}"
                    : 'unknown - use they/them',
                'pronoun_source' => $p['pronoun_source'],
                'last_mentioned_turn' => $p['last_mentioned_turn'],
            ];
        }
        usort($out, fn ($a, $b) => $b['last_mentioned_turn'] <=> $a['last_mentioned_turn']);
        return $out;
    }

    // ── internals ────────────────────────────────────────────────────

    /** "Marcus uses he/him", "Alex goes by they/them", "Jordan's pronouns are she/her" */
    private function captureDeclarations(string $text): void
    {
        $pattern = '/\b([A-Z][a-z]+)\b(?:\'s)?\s+(?:uses|goes by|prefers|pronouns are|uses the pronouns)\s+'
            . '(he|she|they)\s*\/\s*(him|her|them)\b/i';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $this->addPerson($m[1]);
                $this->setPronouns($m[1], $m[2], self::PRONOUN_SOURCE_DECLARED);
            }
        }
    }

    /**
     * Finds people named in the turn, in the order they appear.
     *
     * @return array<int,string> display names, in mention order
     */
    private function captureNames(string $text): array
    {
        $mentioned = [];

        // Relationship binding runs first so that a name introduced by a
        // possessive - "Jennifer's driver is Marcus" - is already known by the
        // time the sentence-initial guard below runs. Otherwise "Jennifer"
        // looks like an ordinary capitalised sentence opener and is skipped.
        $boundRoles = $this->captureRelationships($text);

        // Capitalised words, sentence by sentence.
        //
        // English capitalises the first word of every sentence, so "He is
        // late" and "Call him" would otherwise register "He" and "Call" as
        // people. A sentence-initial word therefore only counts as a name when
        // the conversation already knows that person, or the same word also
        // appears capitalised mid-sentence (where capitalisation is meaningful).
        foreach ($this->sentences($text) as $sentence) {
            if (!preg_match_all('/\b([A-Z][a-z]{1,20})\b/', $sentence, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($m[1] as $i => $hit) {
                $candidate = $hit[0];

                if ($this->isNoiseWord($candidate)) {
                    continue;
                }
                // Never treat a pronoun as a name.
                if (isset(self::PRONOUN_FORMS[strtolower($candidate)])) {
                    continue;
                }

                // A sentence-initial word is only ambiguous when it is also an
                // ordinary English word ("Call him", "Has he arrived"). A word
                // that is not in the common-word list is a name wherever it
                // appears, so genuinely new people introduced at the start of a
                // sentence ("Taylor placed the order") are still captured.
                $isSentenceInitial = ($i === 0 && $hit[1] === 0);
                if ($isSentenceInitial
                    && $this->isCommonWord($candidate)
                    && !isset($this->people[$this->key($candidate)])
                    && !$this->appearsMidSentence($candidate, $text)) {
                    continue;
                }

                $this->addPerson($candidate);
                $mentioned[] = $candidate;
            }
        }

        // Role references: "my driver", "the store manager".
        //
        // Only when the role is not already attached to someone named. Saying
        // "Marcus is my driver" describes ONE person, so registering a separate
        // "Driver" entity would split him in two and let a later "he" bind to
        // the wrong half.
        foreach (self::ROLE_WORDS as $role) {
            if (in_array(strtolower($role), $boundRoles, true)) {
                continue;
            }
            if (preg_match('/\b(?:my|the|our)\s+' . preg_quote($role, '/') . '\b/i', $text)) {
                $label = ucwords($role);
                $this->addPerson($label, $role);
                $mentioned[] = $label;
            }
        }

        foreach ($mentioned as $name) {
            $key = $this->key($name);
            $this->people[$key]['last_mentioned_turn'] = $this->turn;
            $this->people[$key]['mention_count']++;
        }

        return $mentioned;
    }

    /**
     * @return array<int,string> roles now owned by a named person
     */
    private function captureRelationships(string $text): array
    {
        $roles = implode('|', array_map(fn ($r) => preg_quote($r, '/'), self::ROLE_WORDS));
        $bound = [];

        // "Marcus is my driver"
        if (preg_match_all('/\b([A-Z][a-z]+)\s+is\s+(?:my|our|the)\s+(' . $roles . ')\b/i', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $this->addPerson($match[1]);
                $this->people[$this->key($match[1])]['relationship'] = strtolower($match[2]);
                $bound[] = strtolower($match[2]);
            }
        }

        // "Jennifer's driver is Marcus"
        if (preg_match_all('/\b([A-Z][a-z]+)\'s\s+(' . $roles . ')\s+is\s+([A-Z][a-z]+)\b/i', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $this->addPerson($match[1]);
                $this->addPerson($match[3]);
                $this->people[$this->key($match[3])]['relationship'] =
                    strtolower($match[2]) . ' for ' . $match[1];
                $bound[] = strtolower($match[2]);
            }
        }

        return $bound;
    }

    /**
     * Binds each pronoun in the turn to a person, and records that usage as a
     * pronoun signal for them.
     *
     * Two pronouns from *different* sets in one turn ("She called him") must
     * refer to different people, which is what lets a two-person turn resolve
     * without ever consulting the names themselves.
     *
     * @param array<int,string> $mentioned
     * @return array<string, string|null>
     */
    private function resolvePronounsIn(string $text, array $mentioned): array
    {
        $resolved = [];

        if (!preg_match_all('/\b(he|him|his|she|her|hers|they|them|their)\b/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            return $resolved;
        }

        // Distinct pronoun sets used in this turn, in order of appearance.
        $setsInOrder = [];
        foreach ($m[1] as $hit) {
            $setKey = self::PRONOUN_FORMS[strtolower($hit[0])] ?? null;
            if ($setKey !== null && !in_array($setKey, $setsInOrder, true)) {
                $setsInOrder[] = $setKey;
            }
        }

        // Candidates: people named this turn (in order), else recent history.
        $candidates = array_values(array_unique($mentioned));
        if (count($candidates) < count($setsInOrder)) {
            foreach ($this->recentPeople() as $name) {
                if (!in_array($name, $candidates, true)) {
                    $candidates[] = $name;
                }
            }
        }

        foreach ($m[1] as $hit) {
            $surface = strtolower($hit[0]);
            $setKey = self::PRONOUN_FORMS[$surface] ?? null;
            if ($setKey === null) {
                continue;
            }

            $target = null;

            // Someone already established with this pronoun set wins outright.
            $known = $this->candidatesFor($setKey);
            if (count($known) === 1) {
                $target = $known[0]['display_name'];
            } elseif (count($setsInOrder) > 1 && count($candidates) >= count($setsInOrder)) {
                // Distinct sets map onto distinct people, positionally.
                $idx = array_search($setKey, $setsInOrder, true);
                $target = $candidates[$idx] ?? null;
            } elseif (count($candidates) === 1) {
                $target = $candidates[0];
            } else {
                $target = $this->resolve($surface);
            }

            $resolved[$surface] = $target;

            // The speaker used this pronoun for this person: that is them
            // telling us, not us inferring from the name.
            if ($target !== null) {
                $this->setPronouns($target, $setKey, self::PRONOUN_SOURCE_USAGE);
                $this->people[$this->key($target)]['last_mentioned_turn'] = $this->turn;
            }
        }

        return $resolved;
    }

    /** @return array<int, array<string,mixed>> */
    private function candidatesFor(string $setKey): array
    {
        $out = [];
        foreach ($this->people as $p) {
            if (($p['pronouns']['subject'] ?? null) === $setKey) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** @return array<int,string> */
    private function recentPeople(): array
    {
        $people = $this->people;
        uasort($people, fn ($a, $b) => $b['last_mentioned_turn'] <=> $a['last_mentioned_turn']);
        return array_map(fn ($p) => $p['display_name'], array_values($people));
    }

    private function addPerson(string $name, ?string $relationship = null): void
    {
        $key = $this->key($name);
        if (isset($this->people[$key])) {
            if ($relationship !== null && $this->people[$key]['relationship'] === null) {
                $this->people[$key]['relationship'] = $relationship;
            }
            return;
        }

        $this->people[$key] = [
            'id' => $key,
            'display_name' => $name,
            'relationship' => $relationship,
            'pronouns' => null,
            'pronoun_source' => self::PRONOUN_SOURCE_UNKNOWN,
            'last_mentioned_turn' => $this->turn,
            'mention_count' => 0,
        ];
    }

    private function sourceOutranks(string $candidate, string $existing): bool
    {
        $rank = [
            self::PRONOUN_SOURCE_UNKNOWN => 0,
            self::PRONOUN_SOURCE_USAGE => 1,
            self::PRONOUN_SOURCE_PROFILE => 2,
            self::PRONOUN_SOURCE_DECLARED => 3,
        ];
        // Equal rank still wins, so a later correction of the same strength
        // replaces an earlier value.
        return ($rank[$candidate] ?? 0) >= ($rank[$existing] ?? 0);
    }

    private function key(string $name): string
    {
        return strtolower(trim($name));
    }

    /**
     * Ordinary English words that routinely open a sentence capitalised.
     *
     * Only consulted for the first word of a sentence, where capitalisation
     * carries no information. Names are never matched against a list.
     */
    private function isCommonWord(string $word): bool
    {
        static $common = [
            'has', 'have', 'had', 'call', 'called', 'actually', 'any', 'is',
            'are', 'was', 'were', 'where', 'when', 'what', 'who', 'why', 'how',
            'can', 'could', 'would', 'should', 'will', 'shall', 'do', 'does',
            'did', 'get', 'got', 'give', 'let', 'make', 'take', 'tell', 'send',
            'show', 'find', 'check', 'please', 'thanks', 'thank', 'sorry',
            'also', 'still', 'just', 'now', 'then', 'so', 'but', 'and', 'or',
            'if', 'because', 'after', 'before', 'while', 'about', 'over',
            'good', 'great', 'okay', 'sure', 'maybe', 'yes', 'no', 'not',
            'looks', 'look', 'seems', 'sounds', 'need', 'want', 'go', 'going',
        ];
        return in_array(strtolower($word), $common, true);
    }

    /** @return array<int,string> */
    private function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/', trim($text)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    /**
     * Whether the word appears capitalised somewhere other than the start of a
     * sentence, where capitalisation actually signals a proper noun.
     */
    private function appearsMidSentence(string $word, string $text): bool
    {
        foreach ($this->sentences($text) as $sentence) {
            $offset = 0;
            while (($pos = stripos($sentence, $word, $offset)) !== false) {
                if ($pos > 0 && substr($sentence, $pos, strlen($word)) === $word) {
                    return true;
                }
                $offset = $pos + 1;
            }
        }
        return false;
    }

    private function isNoiseWord(string $word): bool
    {
        static $noise = [
            'i', 'the', 'a', 'an', 'and', 'but', 'or', 'if', 'when', 'where',
            'what', 'who', 'why', 'how', 'is', 'are', 'was', 'were', 'this',
            'that', 'these', 'those', 'there', 'here', 'my', 'our', 'your',
            'can', 'could', 'would', 'should', 'please', 'thanks', 'thank',
            'hey', 'hi', 'hello', 'ok', 'okay', 'yes', 'no', 'it', 'we',
            'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
            'january', 'february', 'march', 'april', 'may', 'june', 'july',
            'august', 'september', 'october', 'november', 'december',
            'skylar', 'monique', 'urban', 'goodz',
        ];
        return in_array(strtolower($word), $noise, true);
    }
}
