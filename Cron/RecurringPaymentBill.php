<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\Bill;
use Paytrail\PaymentService\Model\Recurring\TotalConfigProvider;

class RecurringPaymentBill
{
    /**
     * Constructor
     *
     * @param Bill $bill
     */
    public function __construct(
        private Bill $bill,
        private TotalConfigProvider $totalConfigProvider
    ) {
    }

    /**
     * Execute
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        if ($this->totalConfigProvider->isRecurringPaymentEnabled()) {
            $this->bill->process();
        }
    }
}
