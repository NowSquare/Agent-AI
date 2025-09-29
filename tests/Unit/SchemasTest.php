<?php

namespace Tests\Unit;

use App\Schemas\ActionInterpretationSchema;
use App\Schemas\AttachmentSummarizeSchema;
use App\Schemas\ClarifyDraftSchema;
use App\Schemas\LanguageDetectSchema;
use App\Schemas\MemoryExtractSchema;
use App\Schemas\ThreadSummarizeSchema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class SchemasTest extends TestCase
{
    public function test_action_interpretation_schema_accepts_valid_payload(): void
    {
        $data = [
            'action_type' => 'approve',
            'parameters' => ['reason' => 'ok'],
            'scope_hint' => 'thread',
            'confidence' => 0.82,
            'needs_clarification' => false,
            'clarification_prompt' => null,
        ];

        $v = Validator::make($data, ActionInterpretationSchema::rules());
        $this->assertTrue($v->passes(), (string) json_encode($v->errors()->toArray()))
        ;
    }

    public function test_attachment_summarize_schema_accepts_valid_payload(): void
    {
        $data = [
            'title' => 'Q3 Report',
            'gist' => 'Revenue up 12%',
            'key_points' => ['North America grew', 'EMEA flat'],
            'table_hint' => ['columns' => ['Region', 'Revenue']],
        ];
        $v = Validator::make($data, AttachmentSummarizeSchema::rules());
        $this->assertTrue($v->passes(), (string) json_encode($v->errors()->toArray()));
    }

    public function test_clarify_draft_schema_accepts_valid_payload(): void
    {
        $data = [
            'subject' => 'Need one detail',
            'text' => 'Could you confirm the deadline?',
            'html' => '<p>Could you confirm the deadline?</p>',
        ];
        $v = Validator::make($data, ClarifyDraftSchema::rules());
        $this->assertTrue($v->passes(), (string) json_encode($v->errors()->toArray()));
    }

    public function test_language_detect_schema_accepts_valid_payload(): void
    {
        $data = ['language' => 'en', 'confidence' => 0.9];
        $v = Validator::make($data, LanguageDetectSchema::rules());
        $this->assertTrue($v->passes(), (string) json_encode($v->errors()->toArray()));
    }

    public function test_memory_extract_schema_accepts_valid_payload(): void
    {
        $data = [
            'items' => [[
                'key' => 'favorite_color',
                'value' => 'blue',
                'scope' => 'user',
                'ttl_category' => 'seasonal',
                'confidence' => 0.7,
                'provenance' => 'email:123',
            ]],
        ];
        $v = Validator::make($data, MemoryExtractSchema::rules());
        $this->assertTrue($v->passes(), (string) json_encode($v->errors()->toArray()));
    }

    public function test_thread_summarize_schema_accepts_valid_payload(): void
    {
        $data = [
            'summary' => 'Short gist',
            'key_entities' => ['Alice', 'Bob'],
            'open_questions' => ['When is the deadline?'],
        ];
        $v = Validator::make($data, ThreadSummarizeSchema::rules());
        $this->assertTrue($v->passes(), (string) json_encode($v->errors()->toArray()));
    }
}


