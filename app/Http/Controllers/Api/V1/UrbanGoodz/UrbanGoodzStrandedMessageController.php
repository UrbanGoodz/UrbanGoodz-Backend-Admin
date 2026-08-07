<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzStrandedMessage;
use App\Models\UrbanGoodzStrandedRequest;
use App\Services\UrbanGoodzStrandedNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Messaging between a stranded customer and the responder coming to them.
 *
 * The thread opens when a responder is selected and closes when the job ends.
 * Before selection there is nobody to talk to; afterwards there is no reason
 * to keep a private channel open between two strangers.
 *
 * Phone numbers are never exchanged. The specification asks for masked
 * calling, which needs a telephony provider to broker (Twilio Proxy or
 * similar) -- none is connected to this project, so calling is not offered
 * here rather than faked. Messaging, precise location and photos cover the
 * "I can't find you" case, which is what the channel is mostly for.
 */
class UrbanGoodzStrandedMessageController extends Controller
{
    public function __construct(private readonly UrbanGoodzStrandedNotifier $notifier)
    {
    }

    public function index(Request $request, string $record): JsonResponse
    {
        [$stranded, $role, $error] = $this->resolve($request, $record);
        if ($error) {
            return $error;
        }

        $messages = UrbanGoodzStrandedMessage::where('request_id', $stranded->getKey())
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (UrbanGoodzStrandedMessage $m) => $this->present($m));

        // Mark the other side's messages as read for whoever is looking.
        UrbanGoodzStrandedMessage::where('request_id', $stranded->getKey())
            ->where('sender_role', '!=', $role)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'thread_open' => $this->threadIsOpen($stranded),
            'total_size' => $messages->count(),
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, string $record): JsonResponse
    {
        [$stranded, $role, $error] = $this->resolve($request, $record);
        if ($error) {
            return $error;
        }

        if (!$this->threadIsOpen($stranded)) {
            return response()->json([
                'status' => 'error',
                'code' => 'thread_closed',
                'message' => 'This conversation is closed.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:text,location,photo',
            'body' => 'nullable|string|max:2000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy_meters' => 'nullable|numeric|min:0|max:100000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:8192',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $type = $request->input('type', UrbanGoodzStrandedMessage::TYPE_TEXT);

        // Each type has to actually carry its payload, or the recipient gets
        // an empty bubble that tells them nothing.
        if ($type === UrbanGoodzStrandedMessage::TYPE_LOCATION
            && ($request->input('latitude') === null || $request->input('longitude') === null)) {
            return response()->json([
                'status' => 'error',
                'message' => 'A location message needs a latitude and longitude.',
            ], 422);
        }

        if ($type === UrbanGoodzStrandedMessage::TYPE_PHOTO && !$request->hasFile('photo')) {
            return response()->json([
                'status' => 'error',
                'message' => 'A photo message needs a photo.',
            ], 422);
        }

        if ($type === UrbanGoodzStrandedMessage::TYPE_TEXT && trim((string) $request->input('body')) === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Message cannot be empty.',
            ], 422);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            // Private disk, consistent with identity documents.
            $photoPath = Storage::disk('local')->putFileAs(
                "stranded/messages/{$stranded->getKey()}",
                $request->file('photo'),
                Str::random(24) . '.' . $request->file('photo')->getClientOriginalExtension()
            );
        }

        $message = UrbanGoodzStrandedMessage::create([
            'request_id' => $stranded->getKey(),
            'sender_role' => $role,
            'sender_id' => $request->user()?->id,
            'type' => $type,
            'body' => $request->input('body'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'accuracy_meters' => $request->input('accuracy_meters'),
            'photo_path' => $photoPath,
        ]);

        $this->notifier->messageReceived($stranded, $role, $this->preview($message));

        return response()->json([
            'status' => 'success',
            'data' => $this->present($message),
        ], 201);
    }

    /**
     * Open from selection until the job reaches a terminal state.
     */
    private function threadIsOpen(UrbanGoodzStrandedRequest $stranded): bool
    {
        return $stranded->selected_offer_id !== null && !$stranded->isTerminal();
    }

    /**
     * Resolves the request and establishes which side of it the caller is on.
     * Anyone who is neither the customer nor the assigned responder gets a 404
     * rather than a 403 -- there is no reason to confirm that a stranger's
     * rescue exists.
     */
    private function resolve(Request $request, string $record): array
    {
        $userId = (int) ($request->user()?->id ?? 0);

        $stranded = UrbanGoodzStrandedRequest::query()
            ->where(fn ($q) => $q->where('uuid', $record)->orWhere('request_number', $record))
            ->first();

        $notFound = response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);

        if (!$stranded || $userId <= 0) {
            return [null, null, $notFound];
        }

        if ((int) $stranded->user_id === $userId) {
            return [$stranded, UrbanGoodzStrandedMessage::ROLE_CUSTOMER, null];
        }

        if ((int) $stranded->assigned_responder_id === $userId) {
            return [$stranded, UrbanGoodzStrandedMessage::ROLE_RESPONDER, null];
        }

        return [null, null, $notFound];
    }

    private function preview(UrbanGoodzStrandedMessage $m): string
    {
        return match ($m->type) {
            UrbanGoodzStrandedMessage::TYPE_LOCATION => 'Shared their exact location',
            UrbanGoodzStrandedMessage::TYPE_PHOTO => 'Sent a photo',
            default => Str::limit((string) $m->body, 80),
        };
    }

    private function present(UrbanGoodzStrandedMessage $m): array
    {
        return [
            'id' => $m->id,
            'sender_role' => $m->sender_role,
            'type' => $m->type,
            'body' => $m->body,
            'latitude' => $m->latitude,
            'longitude' => $m->longitude,
            'accuracy_meters' => $m->accuracy_meters,
            'has_photo' => $m->photo_path !== null,
            'read_at' => $m->read_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
