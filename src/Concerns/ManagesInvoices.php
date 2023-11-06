<?php

namespace InitAfricaHQ\Cashier\Concerns;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use InitAfricaHQ\Cashier\Invoice;
use InitAfricaHQ\Cashier\Paystack;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ManagesInvoices
{
    /**
     * Invoice the customer for the given amount.
     *
     * @param  string  $description
     * @param  int  $amount
     *
     * @throws Exception
     */
    public function tab($description, $amount, array $options = [])
    {
        if (! $this->customer->paystack_id) {
            throw new InvalidArgumentException(class_basename($this).' is not a Paystack customer. See the createAsCustomer method.');
        }

        if (! array_key_exists('due_date', $options)) {
            throw new InvalidArgumentException('No due date provided.');
        }

        $options = array_merge([
            'customer' => $this->customer->paystack_id,
            'amount' => $amount,
            'currency' => $this->preferredCurrency(),
            'description' => $description,
        ], $options);

        $options['due_date'] = Carbon::parse($options['due_date'])->format('c');

        return Paystack::createInvoice($options);
    }

    /**
     * Invoice the billable entity outside of regular billing cycle.
     *
     * @param  string  $description
     * @param  int  $amount
     *
     * @throws Exception
     */
    public function invoiceFor($description, $amount, array $options = [])
    {
        return $this->tab($description, $amount, $options);
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return Invoice|null
     */
    public function findInvoice($id)
    {
        try {
            $response = Paystack::findInvoice($id);
            $invoice = $response->json('data');

            if ($invoice['customer']['id'] != $this->paystack_id) {
                return;
            }

            return new Invoice($this, $invoice);
        } catch (Exception $e) {
            //
        }
    }

    /**
     * Find an invoice or throw a 404 error.
     *
     * @param  string  $id
     */
    public function findInvoiceOrFail($id): Invoice
    {
        $invoice = $this->findInvoice($id);

        if (is_null($invoice)) {
            throw new NotFoundHttpException();
        }

        return $invoice;
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function downloadInvoice($id, array $data)
    {
        return $this->findInvoiceOrFail($id)->download($data);
    }

    /**
     * Get a collection of the entity's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     *
     * @throws \Exception
     */
    public function invoices($options = []): Collection
    {
        $this->assertCustomerExists();

        $invoices = [];
        $parameters = array_merge(['customer' => $this->customer->paystack_id], $options);

        $response = Paystack::fetchInvoices($parameters);
        $paystackInvoices = $response->json('data');

        // Here we will loop through the Paystack invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Paystack objects are. Then, we'll return the array.
        if (! is_null($paystackInvoices && ! empty($paystackInvoices))) {
            foreach ($paystackInvoices as $invoice) {
                $invoices[] = new Invoice($this, $invoice);
            }
        }

        return new Collection($invoices);
    }

    /**
     * Get an array of the entity's invoices.
     */
    public function invoicesOnlyPending(array $parameters = []): Collection
    {
        $parameters['status'] = 'pending';

        return $this->invoices($parameters);
    }

    /**
     * Get an array of the entity's invoices.
     */
    public function invoicesOnlyPaid(array $parameters = []): Collection
    {
        $parameters['paid'] = true;

        return $this->invoices($parameters);
    }
}
