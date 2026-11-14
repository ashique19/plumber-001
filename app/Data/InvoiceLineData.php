<?php

namespace App\Data;

use App\Support\Invoicing\InvoiceLineMath;

readonly class InvoiceLineData
{
  public function __construct(
    public string $name,
    public float $quantity,
    public float $unitPriceExVat,
    public float $vatRate,
    public float $discountExVat = 0.0,
    public ?string $nameAr = null,
  ) {}

  public function netExVat(): float
  {
    return InvoiceLineMath::lineNetExVat(
      $this->unitPriceExVat,
      $this->quantity,
      $this->discountExVat,
    );
  }

  public function vatAmount(): float
  {
    return InvoiceLineMath::lineVatAmount($this->netExVat(), $this->vatRate);
  }

  public function lineIncVat(): float
  {
    return InvoiceLineMath::lineIncVat($this->netExVat(), $this->vatRate);
  }
}
