<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PostmarkSepaNoticeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PostmarkMailWebhookController extends Controller
{
    public function __invoke(Request $request, PostmarkSepaNoticeWebhookService $service): JsonResponse
    {
        $username = (string) config('services.postmark.webhook_username');
        $password = (string) config('services.postmark.webhook_password');
        if ($username === '' || $password === '') {
            return response()->json(['message' => __('webhooks.not_configured')], 503);
        }
        if (! hash_equals($username, (string) $request->getUser()) || ! hash_equals($password, (string) $request->getPassword())) {
            return response()->json(['message' => __('webhooks.unauthorized')], 401, ['WWW-Authenticate' => 'Basic realm="Postmark webhook"']);
        }

        $allowedIps = config('services.postmark.webhook_ips', []);
        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['message' => __('webhooks.forbidden')], 403);
        }

        $payload = $request->json()->all();
        $eventType = strtolower((string) ($payload['RecordType'] ?? ''));
        $recipientField = $eventType === 'delivery' ? 'Recipient' : 'Email';
        $occurredAtField = $eventType === 'delivery' ? 'DeliveredAt' : 'BouncedAt';
        $validator = Validator::make($payload, [
            'RecordType' => ['required', 'string', Rule::in(['Delivery', 'Bounce'])],
            'MessageID' => ['required', 'string', 'max:512', 'regex:/\S/u'],
            $recipientField => ['required', 'string', 'email:rfc', 'max:254'],
            $occurredAtField => ['required', 'date'],
            'ID' => ['sometimes', 'integer'],
            'Type' => ['sometimes', 'string', 'max:80'],
            'TypeCode' => ['sometimes', 'integer'],
            'Inactive' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => __('webhooks.invalid_payload')], 400);
        }

        return response()->json(['status' => $service->handle($validator->validated())], 202);
    }
}
