<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\FonnteWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * FonnteWebhookController
 *
 * Receives incoming WhatsApp messages from the Fonnte API webhook.
 * 100% AI Driven using GroqAiService.
 */
class FonnteWebhookController extends Controller
{
    private string $webhookToken;
    private string $fonnteToken;
    protected FonnteWebhookService $webhookService;

    public function __construct(FonnteWebhookService $webhookService)
    {
        $this->webhookToken = config('services.fonnte.webhook_token', '');
        $this->fonnteToken = config('services.fonnte.token', '');
        $this->webhookService = $webhookService;
    }

    /**
     * POST /api/webhook/fonnte
     */
    public function handle(Request $request): JsonResponse
    {
        // ── Validate Fonnte webhook token ──────────────────────────
        if ($this->webhookToken && $request->header('X-Fonnte-Token') !== $this->webhookToken) {
            Log::warning('Fonnte webhook: invalid token');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $sender  = $request->input('sender');   // WA number e.g. "628123456789"
        $message = trim($request->input('message', ''));

        $replyText = $this->webhookService->handleIncomingMessage($sender, $message);

        // Explicitly send reply via Fonnte API to ensure delivery
        if ($this->fonnteToken && $sender && $replyText) {
            Http::withHeaders([
                'Authorization' => $this->fonnteToken,
            ])->post('https://api.fonnte.com/send', [
                'target'  => $sender,
                'message' => $replyText,
            ]);
        }

        return response()->json([
            'status' => true,
            'reply' => $replyText
        ]);
    }
}
