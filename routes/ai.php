<?php

use App\Mcp\Servers\PsvTravelServer;
use Laravel\Mcp\Facades\Mcp;

// Máy chủ MCP công khai cho trợ lý AI: https://api.psvtravel.com/mcp
// (gói laravel/mcp tự nạp file này). Giới hạn tần suất riêng "mcp".
Mcp::web('/mcp', PsvTravelServer::class)->middleware('throttle:mcp');
