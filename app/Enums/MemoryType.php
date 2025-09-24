<?php

namespace App\Enums;

/**
 * MemoryType provides a typed classification for curated memories.
 * This helps downstream retrieval, analytics, and UI presentation.
 */
enum MemoryType: string
{
    case Decision = 'Decision';
    case Insight  = 'Insight';
    case Fact     = 'Fact';
}


