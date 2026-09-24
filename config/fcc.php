<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Free Claude Code (FCC) Service Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration connects the Laravel AI Gateway Adapter with the
    | Free Claude Code proxy service (https://github.com/Alishahryar1/free-claude-code).
    |
    */

    'base_url' => env('FCC_BASE_URL', 'http://127.0.0.1:8082'),
    'auth_token' => env('FCC_AUTH_TOKEN', 'freecc'),
    'timeout' => env('FCC_TIMEOUT', 120),
    'connect_timeout' => env('FCC_CONNECT_TIMEOUT', 10),
    'retry_attempts' => env('FCC_RETRY_ATTEMPTS', 2),
    'retry_delay_ms' => env('FCC_RETRY_DELAY_MS', 1000),

    // Coding Agents supported in FCC
    'supported_agents' => [
        'claude' => [
            'name' => 'Claude Code',
            'slug' => 'claude',
            'harness' => 'claude',
            'description' => 'Official Claude Code CLI agent routing via FCC proxy',
        ],
        'codex' => [
            'name' => 'Codex',
            'slug' => 'codex',
            'harness' => 'codex',
            'description' => 'OpenAI Codex agent harness for autonomous repository tasks',
        ],
        'opencode' => [
            'name' => 'OpenCode',
            'slug' => 'opencode',
            'harness' => 'opencode',
            'description' => 'OpenCode open-source terminal coding agent',
        ],
        'pi' => [
            'name' => 'Pi',
            'slug' => 'pi',
            'harness' => 'pi',
            'description' => 'Pi fast lightweight interactive coding agent',
        ],
        'cline' => [
            'name' => 'Cline',
            'slug' => 'cline',
            'harness' => 'cline',
            'description' => 'Autonomous coding agent supporting tools and browser automation',
        ],
        'hermes' => [
            'name' => 'Hermes',
            'slug' => 'hermes',
            'harness' => 'hermes',
            'description' => 'NousResearch Hermes Agent for complex multi-step reasoning',
        ],
        'dsh' => [
            'name' => 'DeepSeek Harness',
            'slug' => 'dsh',
            'harness' => 'dsh',
            'description' => 'DeepSeek AI reasoning and code generation harness',
        ],
        'grok' => [
            'name' => 'Grok Build',
            'slug' => 'grok',
            'harness' => 'grok',
            'description' => 'xAI Grok Build agent harness',
        ],
        'muse' => [
            'name' => 'Muse Code',
            'slug' => 'muse',
            'harness' => 'muse',
            'description' => 'Meta Muse Code and Muse Spark code intelligence agent',
        ],
        'aider' => [
            'name' => 'Aider',
            'slug' => 'aider',
            'harness' => 'aider',
            'description' => 'Pair programming terminal agent for git repository editing',
        ],
    ],
];
