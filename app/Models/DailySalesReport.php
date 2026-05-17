<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesReport extends Model
{
    protected $fillable = [
        'report_date', 'total_orders', 'total_revenue', 'items_sold', 'top_products'
    ];

    protected $casts = [
        'report_date'   => 'date',
        'total_revenue' => 'decimal:2',
        'top_products'  => 'array',
    ];
}
