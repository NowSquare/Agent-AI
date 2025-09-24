<?php
/**
 * Store one step in the activity trace (who did what, when, and how long).
 * Plain: Think of this like a flight log—each line is one move an AI made.
 * How this fits in:
 * - Saved whenever a model/tool is called or a decision is logged.
 * - Activity UI reads this table to show the full trace.
 * - Metrics and audits summarize these rows.
 * Key terms defined here:
 * - tokens_*: rough size of input/output (how much text), helps with cost/latency.
 * - agent_role: Planner/Worker/Critic/Arbiter (which hat the AI was wearing).
 * - round_no: debate round counter (0 means not part of a debate).
 * - vote_score: how strong a vote was for a candidate in a round.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Purpose: Represents one recorded step in the system's reasoning or actions.
 * Responsibilities:
 * - Persist metadata and JSON I/O for transparency
 * - Expose relationships to related records (thread, user, etc.)
 * Collaborators:
 * - UI (Activity pages), MultiAgentOrchestrator, AgentProcessor
 */
class AgentStep extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id','thread_id','email_message_id','action_id','contact_id','user_id',
        'role','provider','model','step_type','input_json','output_json',
        'tokens_input','tokens_output','tokens_total','latency_ms','confidence',
        'agent_role','round_no','coalition_id','vote_score','decision_reason',
    ];

    protected function casts(): array
    {
        return [
            'input_json' => 'array',
            'output_json' => 'array',
            'confidence' => 'float',
            'vote_score' => 'float',
        ];
    }

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function thread(): BelongsTo { return $this->belongsTo(Thread::class); }
    public function emailMessage(): BelongsTo { return $this->belongsTo(EmailMessage::class); }
    public function action(): BelongsTo { return $this->belongsTo(Action::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * Summary: limit visibility so a user only sees steps from their own threads.
     * @param  \Illuminate\Database\Eloquent\Builder $query   base query
     * @param  User $user                                      the viewer
     * @return \Illuminate\Database\Eloquent\Builder         filtered query
     * Example:
     *   $steps = AgentStep::visibleTo(auth()->user())->latest()->get();
     */
    public function scopeVisibleTo($query, User $user)
    {
        return $query
            ->where('account_id', $user->memberships()->pluck('account_id'))
            ->whereIn('thread_id', function($q) use ($user) {
                $q->select('threads.id')
                  ->from('threads')
                  ->join('email_messages','email_messages.thread_id','=','threads.id')
                  ->join('contacts','contacts.email','=','email_messages.from_email')
                  ->join('contact_links','contact_links.contact_id','=','contacts.id')
                  ->where('contact_links.user_id', $user->id);
            });
    }
}


