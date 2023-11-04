<?php

namespace InitAfricaHQ\Cashier\Concerns;

use Illuminate\Support\Collection;
use InitAfricaHQ\Cashier\Card;

trait ManagesCards
{
    /**
     * Get a collection of the entity's payment methods.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function cards($parameters = [])
    {
        $cards = [];
        $paystackAuthorizations = $this->asPaystackCustomer()->authorizations;
        if (! is_null($paystackAuthorizations)) {
            foreach ($paystackAuthorizations as $card) {
                if ($card['channel'] == 'card') {
                    $cards[] = new Card($this, $card);
                }
            }
        }

        return new Collection($cards);
    }

    /**
     * Deletes the entity's payment methods.
     *
     * @return void
     */
    public function deleteCards()
    {
        $this->cards()->each(function (Card $card) {
            $card->delete();
        });
    }
}
