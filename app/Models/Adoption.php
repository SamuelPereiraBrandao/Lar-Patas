<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adoption extends Model
{
    protected $fillable = ['user_id', 'pet_id', 'applicant_name', 'email', 'phone', 'housing_type', 'has_other_pets', 'message', 'status', 'pickup_at', 'pickup_timezone', 'pickup_location', 'pickup_message', 'pickup_code', 'released_at', 'scheduled_by', 'released_by', 'cancelled_at', 'cancellation_reason', 'reschedule_requested_at', 'requested_pickup_at', 'reschedule_reason', 'reminded_at', 'followup_sent_at', 'followup_completed_at', 'adaptation_status', 'adaptation_notes', 'support_message', 'support_replied_at', 'followup_resolved_at'];

    protected $casts = ['has_other_pets' => 'boolean', 'pickup_at' => 'datetime', 'released_at' => 'datetime', 'pickup_code' => 'encrypted', 'cancelled_at' => 'datetime', 'requested_pickup_at' => 'datetime', 'reminded_at' => 'datetime', 'followup_sent_at' => 'datetime'];

    protected $hidden = ['pickup_code'];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
