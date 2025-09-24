<?php

namespace App\Enums;

/**
 * AgentRole defines the functional role an agent plays within the
 * multi-agent protocol. These values are stored in agent_steps.agent_role
 * to make Activity auditing and analytics straightforward.
 */
enum AgentRole: string
{
    case Planner = 'Planner';
    case Worker  = 'Worker';
    case Critic  = 'Critic';
    case Arbiter = 'Arbiter';
}


