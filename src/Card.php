<?php

namespace InitAfricaHQ\Cashier;

use Exception;
use Illuminate\Database\Eloquent\Model;

class Card
{
    /**
     * The Paystack model instance.
     *
     * @var Model
     */
    protected $billable;

    /**
     * The Paystack card instance.
     */
    protected $card;

    /**
     * Create a new card instance.
     *
     * @param  Model  $billable
     * @return void
     */
    public function __construct($billable, $card)
    {
        $this->billable = $billable;
        $this->card = (object) $card;
    }

    /**
     * Check the payment Method have funds for the payment you seek.
     *
     * @throws Exception
     */
    public function check($amount)
    {
        $data = [];
        $data['email'] = $this->billable->email;
        $data['amount'] = $amount;
        $data['authorization_code'] = $this->card->authorization_code;

        if ($this->isReusable) {
            return Paystack::checkAuthorization($data);
        }

        throw new Exception('Payment Method is no longer reusable.');
    }

    /**
     * Delete the payment Method.
     */
    public function delete()
    {
        return Paystack::deactivateAuthorization($this->card->authorization_code);
    }

    /**
     * Get the Paystack payment authorization object.
     */
    public function asPaystackAuthorization()
    {
        return $this->card;
    }

    /**
     * Dynamically get values from the Paystack card.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->card->{$key};
    }
}
