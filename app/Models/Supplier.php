<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Supplier extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'supplier_code', 'name', 'category',
        'contact_person', 'email', 'secondary_email',
        'phone', 'phone2', 'whatsapp', 'whatsapp_number',
        'address', 'website',
        'tax_number', 'credit_terms', 'credit_days',
        'is_active', 'remarks',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function routeNotificationFor(string $channel, mixed $notification = null): ?string
    {
        return $this->whatsapp_number;
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function goodsReceiptNotes()
    {
        return $this->hasMany(GoodsReceiptNote::class);
    }

    public function rfqInvitations()
    {
        return $this->hasMany(RfqInvitation::class);
    }

    public function supplierQuotes()
    {
        return $this->hasMany(SupplierQuote::class);
    }

    public function hasRelatedRecords(): bool
    {
        return $this->purchaseOrders()->exists()
            || $this->invoices()->exists()
            || $this->payments()->exists()
            || $this->goodsReceiptNotes()->exists()
            || $this->rfqInvitations()->exists()
            || $this->supplierQuotes()->exists();
    }
}
