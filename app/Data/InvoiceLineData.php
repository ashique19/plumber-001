<?php

namespace App\Data;

use App\Support\VatPricing;

readonly class InvoiceLineData
{
  public function __construct(
    public string $name,
    public float $quantity,
    public float $unitPriceExVat,
    public float $vatRate,
    public float $discountExVat = 0.0,
  ) {}

  /** @return array{line_inc: float, vat: float, net: float} */
  public function shelfAmounts(): array
  {
    return VatPricing::lineAmountsFromUnitEx(
      $this->unitPriceExVat,
      $this->quantity,
      $this->vatRate,
      $this->discountExVat,
    );
  }

  public function netExVat(): float
  {
    return $this->shelfAmounts()['net'];
  }

  public function vatAmount(): float
  {
    return $this->shelfAmounts()['vat'];
  }

  public function lineIncVat(): float
  {
    return $this->shelfAmounts()['line_inc'];
  }
}
