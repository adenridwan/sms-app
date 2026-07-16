<?php

namespace App\Infrastructure\Persistence\Eloquent\Auth;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_profiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'gender',
        'birth_date',
        'birth_place',
        'religion',
        'address',
        'city',
        'province',
        'postal_code',
        'country',
        'nationality',
        'id_number',
        'blood_type',
        'medical_notes',
    ];

    /**
     * Get the user that owns this profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
