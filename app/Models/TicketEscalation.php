<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketEscalation extends Model
{
    protected $fillable = [
        'ticket_id',
        'escalation_level',
        'escalated_at',
        'notification_type',
        'is_resolved',
        'resolved_at',
    ];

    protected $casts = [
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'is_resolved' => 'boolean',
    ];

    /**
     * Ticket relationship
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Scope: Unresolved escalations
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope: By escalation level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('escalation_level', $level);
    }

    /**
     * Scope: Warning level
     */
    public function scopeWarning($query)
    {
        return $query->where('escalation_level', 'warning');
    }

    /**
     * Scope: Escalated level
     */
    public function scopeEscalated($query)
    {
        return $query->where('escalation_level', 'escalated');
    }
}
