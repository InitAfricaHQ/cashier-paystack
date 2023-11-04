<?php

namespace InitAfricaHQ\Cashier;

use InitAfricaHQ\Cashier\Concerns\ManagesCards;
use InitAfricaHQ\Cashier\Concerns\ManagesCustomer;
use InitAfricaHQ\Cashier\Concerns\ManagesInvoices;
use InitAfricaHQ\Cashier\Concerns\ManagesPayments;
use InitAfricaHQ\Cashier\Concerns\ManagesSubscriptions;

trait Billable
{
    use ManagesCards;
    use ManagesCustomer;
    use ManagesInvoices;
    use ManagesPayments;
    use ManagesSubscriptions;
}
