<?php

namespace App\Support\Privacy;

enum ProcessingPurpose: string
{
    case ProductOperation = 'product_operation';
    case Training = 'training';
    case Organization = 'organization';
    case Billing = 'billing';
    case Support = 'support';
    case Security = 'security';
    case Analytics = 'analytics';
    case Marketing = 'marketing';
}
