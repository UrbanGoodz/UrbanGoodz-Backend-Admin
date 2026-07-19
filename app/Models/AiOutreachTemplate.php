<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiOutreachTemplate extends Model
{
    protected $fillable = [
        'slug', 'name', 'subject', 'body', 'category', 'sequence_day', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sequence_day' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSequence($query)
    {
        return $query->whereNotNull('sequence_day')->orderBy('sequence_day');
    }

    /**
     * Render the template body with variable substitution.
     */
    public function render(array $variables): string
    {
        $body = $this->body;
        foreach ($variables as $key => $value) {
            $body = str_replace('{{' . $key . '}}', (string) $value, $body);
        }
        return $body;
    }

    public function renderSubject(array $variables): string
    {
        $subject = $this->subject;
        foreach ($variables as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
        }
        return $subject;
    }
}
