<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'ticket_id',
    'message',
    'type',
    'est_lue',
    'date_envoi',
])]
class Notification extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'est_lue'    => 'boolean',
            'date_envoi' => 'datetime',
        ];
    }

    const TYPES = [
        'nouveau_ticket',
        'ticket_assigne',
        'nouveau_commentaire',
        'statut_change',
        'ticket_resolu',
        'rappel',
    ];

    // ─── Relations ───────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────
    #[Scope]
    public function nonLues(Builder $query)
    {
        $query->where('est_lue', false);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function marquerLue(): void
    {
        $this->update(['est_lue' => true]);
    }

    // Créer une notification facilement depuis n'importe où
    public static function envoyer(string $userId, string $message, string $type, ?string $ticketId = null): self
    {
        return self::create([
            'user_id'   => $userId,
            'ticket_id' => $ticketId,
            'message'   => $message,
            'type'      => $type,
            'date_envoi' => now(),
        ]);
    }
}
