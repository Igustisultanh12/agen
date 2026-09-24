<?php

namespace Database\Seeders;

use App\Models\CodingAgent;
use Illuminate\Database\Seeder;

class CodingAgentSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            [
                'name' => 'Claude Code',
                'slug' => 'claude-code',
                'harness_type' => 'claude',
                'description' => 'Anthropic-style agentic workflow with rich tool integration and iterative refactoring.',
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'name' => 'Codex',
                'slug' => 'codex',
                'harness_type' => 'codex',
                'description' => 'OpenAI Codex autonomous repository explorer and code architect.',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'OpenCode',
                'slug' => 'opencode',
                'harness_type' => 'opencode',
                'description' => 'OpenCode multi-provider agent with deep codebase context.',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Pi',
                'slug' => 'pi',
                'harness_type' => 'pi',
                'description' => 'Fast, conversational programming assistant for rapid prototyping.',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Cline',
                'slug' => 'cline',
                'harness_type' => 'cline',
                'description' => 'Autonomous developer agent capable of executing terminal commands and tool calls.',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Hermes',
                'slug' => 'hermes',
                'harness_type' => 'hermes',
                'description' => 'NousResearch advanced multi-step reasoning agent for tough software algorithms.',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'DeepSeek Harness',
                'slug' => 'deepseek-harness',
                'harness_type' => 'dsh',
                'description' => 'DeepSeek reasoning harness optimized for complex bug diagnosis and code generation.',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Grok Build',
                'slug' => 'grok-build',
                'harness_type' => 'grok',
                'description' => 'xAI Grok Build fast structural code builder and tester.',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Muse Code',
                'slug' => 'muse-code',
                'harness_type' => 'muse',
                'description' => 'Meta Muse Code and Muse Spark code intelligence agent.',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Aider',
                'slug' => 'aider',
                'harness_type' => 'aider',
                'description' => 'Pair programming git assistant that commits clean diffs automatically.',
                'is_active' => true,
                'is_default' => false,
            ],
        ];

        foreach ($agents as $agent) {
            CodingAgent::updateOrCreate(['slug' => $agent['slug']], $agent);
        }
    }
}
