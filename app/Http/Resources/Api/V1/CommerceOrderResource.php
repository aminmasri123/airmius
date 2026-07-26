<?php

namespace App\Http\Resources\Api\V1;

use App\Support\CommerceOrderSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class CommerceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $bankTransfer = data_get($payload, 'bank_transfer', []);
        $shippingAddress = data_get($payload, 'shipping_address', []);
        $support = app(CommerceOrderSupport::class)->summary($this->resource);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'club_id' => $this->club_id,
            'orderable_type' => $this->orderable_type,
            'orderable_id' => $this->orderable_id,
            'type' => $this->type,
            'provider' => $this->provider,
            'billing_interval' => $this->billing_interval,
            'status' => $this->status,
            'shipping_status' => $this->shipping_status,
            'shipping_carrier' => $this->shipping_carrier,
            'shipping_label_url' => $this->shipping_label_url,
            'currency' => $this->currency,
            'amount_cents' => $this->amount_cents,
            'item_gross_cents' => $this->item_gross_cents,
            'net_cents' => $this->net_cents,
            'tax_cents' => $this->tax_cents,
            'shipping_cents' => $this->shipping_cents,
            'commission_cents' => $this->commission_cents,
            'refunded_cents' => $this->refunded_cents,
            'invoice_number' => $this->invoice_number,
            'credit_note_number' => $this->credit_note_number,
            'issue_status' => $this->issue_status,
            'issue_note' => $this->issue_note,
            'issue_response' => $this->issue_response,
            'payment_reference' => $this->payment_reference,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'checkout_url' => $this->checkout_url,
            'payment_action' => [
                'type' => $this->provider === 'bank_transfer' ? 'bank_transfer' : 'redirect',
                'url' => $this->provider === 'bank_transfer' ? null : $this->checkout_url,
            ],
            'bank_transfer' => $this->provider === 'bank_transfer' ? [
                'account_holder' => data_get($bankTransfer, 'bank_account_holder'),
                'bank_name' => data_get($bankTransfer, 'bank_name'),
                'iban' => data_get($bankTransfer, 'iban'),
                'bic' => data_get($bankTransfer, 'bic'),
                'reference' => $this->payment_reference,
                'due_at' => $this->due_at?->toJSON(),
            ] : null,
            'shipping_address' => is_array($shippingAddress) ? [
                'country' => data_get($shippingAddress, 'country'),
                'state' => data_get($shippingAddress, 'state'),
                'postal_code' => data_get($shippingAddress, 'postal_code'),
                'city' => data_get($shippingAddress, 'city'),
                'street' => data_get($shippingAddress, 'street'),
                'house_number' => data_get($shippingAddress, 'house_number'),
            ] : null,
            'support' => [
                ...$support,
                'can_cancel' => in_array($this->type, ['marketplace_product', 'marketplace_cart'], true)
                    && in_array($this->status, ['pending', 'awaiting_transfer', 'completed'], true)
                    && ! in_array($this->shipping_status, ['shipped', 'delivered'], true),
            ],
            'documents' => [
                'invoice' => [
                    'available' => filled($this->invoice_number),
                    'number' => $this->invoice_number,
                    'url' => filled($this->invoice_number)
                        ? URL::temporarySignedRoute(
                            'commerce.documents.signed',
                            now()->addMinutes(5),
                            ['order' => $this->id, 'type' => 'invoice'],
                        )
                        : null,
                ],
                'credit_note' => [
                    'available' => filled($this->credit_note_number),
                    'number' => $this->credit_note_number,
                    'url' => filled($this->credit_note_number)
                        ? URL::temporarySignedRoute(
                            'commerce.documents.signed',
                            now()->addMinutes(5),
                            ['order' => $this->id, 'type' => 'credit-note'],
                        )
                        : null,
                ],
            ],
            'due_at' => $this->due_at?->toJSON(),
            'completed_at' => $this->completed_at?->toJSON(),
            'shipped_at' => $this->shipped_at?->toJSON(),
            'delivered_at' => $this->delivered_at?->toJSON(),
            'club' => new ClubResource($this->whenLoaded('club')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'orderable_type' => $item->orderable_type,
                'orderable_id' => $item->orderable_id,
                'title' => $item->title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_gross_cents' => $item->unit_gross_cents,
                'shipping_cents' => $item->shipping_cents,
                'net_cents' => $item->net_cents,
                'tax_cents' => $item->tax_cents,
                'total_cents' => $item->total_cents,
                'currency' => $item->currency,
                'tax_rate_percent' => $item->tax_rate_percent,
                'is_shippable' => $item->is_shippable,
            ])->values()),
            'return_requests' => $this->whenLoaded('returnRequests', fn () => $this->returnRequests->map(fn ($returnRequest) => [
                'id' => $returnRequest->id,
                'commerce_order_item_id' => $returnRequest->commerce_order_item_id,
                'status' => $returnRequest->status,
                'reason' => $returnRequest->reason,
                'resolution_note' => $returnRequest->resolution_note,
                'quantity' => $returnRequest->quantity,
                'requested_amount_cents' => $returnRequest->requested_amount_cents,
                'approved_amount_cents' => $returnRequest->approved_amount_cents,
                'currency' => $returnRequest->currency,
                'requested_at' => $returnRequest->requested_at?->toJSON(),
                'approved_at' => $returnRequest->approved_at?->toJSON(),
                'rejected_at' => $returnRequest->rejected_at?->toJSON(),
                'received_at' => $returnRequest->received_at?->toJSON(),
                'refunded_at' => $returnRequest->refunded_at?->toJSON(),
            ])->values()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
