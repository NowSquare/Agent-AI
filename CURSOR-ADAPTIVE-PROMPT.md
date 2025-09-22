# Adaptive Prompt for Agent AI Dev

Reference: @CURSOR-CONTEXT.md @CURSOR-README.md

Task Mode: [PLAN|EXECUTE|DEBUG|TEST]  // Required: PLAN for audit/gaps; EXECUTE for impl; DEBUG for fixes; TEST for unit/feature
Scope: [FULL|PHASE:Inbound|COMPONENT:Webhook]  // Required: Narrow for focus
Time Budget: [1hr|2hr|4hr]  // Optional: Limits tasks (default 2hr)

Instructions:
- Audit/Implement against CURSOR-README.md requirements and workspace state.
- Dependencies: Check existing before adding; update .env.* as needed.
- MCP: Use for all structured LLM ops.

If Mode=PLAN:
    Output Sections:
    - GAPS: Table | Component | Required | Present | Notes |
    - PRIORITY TASKS: 3-5 bite-sized (title, criteria, files/commands, deps)
    - TESTS: 1-2 PHPUnit names per task

If Mode=EXECUTE:
    Output Sections:
    - PHASES: Break into A/B/C; per phase: summary, shell commands, Cursor diffs (inline for files), verification checklist, git commit msg + files

If Mode=DEBUG:
    Output: Issue analysis, fix diffs/commands, verification

If Mode=TEST:
    Output: Generated test code diffs, run commands, expected outputs

Begin with concise summary. No role-playing. Output labeled sections only.