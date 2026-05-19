<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'contact_person', 'email', 'phone', 'whatsapp_number', 'address', 'tax_number', 'credit_limit', 'outstanding_balance', 'is_active'];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function routeNotificationFor(string $channel, mixed $notification = null): ?string
    {
        return $this->whatsapp_number;
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function salesInvoices()
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function paymentReceipts()
    {
        return $this->hasMany(PaymentReceipt::class);
    }
}
