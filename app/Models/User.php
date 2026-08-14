<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['nom', 'prenom', 'email', 'password', 'role', 'telephone', 'actif'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'actif' => 'boolean',
        ];
    }

    public function technicien(): HasOne
    {
        return $this->hasOne(Technicien::class);
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function commentaires()
    {
        return $this->hasMany(Commentaire::class, 'auteur_id');
    }

    public function articlesBase(): HasMany
    {
        return $this->hasMany(ArticleBase::class, 'auteur_id');
    }

    public function statistiques()
    {
        return $this->hasMany(Statistique::class, 'genere_par_id');
    }

    // Tickets créés par cet utilisateur (relation polymorphe)
    public function ticketsCrees()
    {
        return $this->morphMany(Ticket::class, 'createur');
    }

    // ─── Helpers de rôle ─────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'administrateur';
    }

    public function isTechnicien(): bool
    {
        return $this->role === 'technicien';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }
}
