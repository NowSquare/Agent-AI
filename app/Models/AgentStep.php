<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStep extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id','thread_id','email_message_id','action_id','contact_id','user_id',
        'role','provider','model','step_type','input_json','output_json',
        'tokens_input','tokens_output','tokens_total','latency_ms','confidence',
    ];

    protected function casts(): array
    {
        return [
            'input_json' => 'array',
            'output_json' => 'array',
            'confidence' => 'float',
        ];
    }

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function thread(): BelongsTo { return $this->belongsTo(Thread::class); }
    public function emailMessage(): BelongsTo { return $this->belongsTo(EmailMessage::class); }
    public function action(): BelongsTo { return $this->belongsTo(Action::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

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


