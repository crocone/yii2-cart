<?php

namespace crocone\cart\storage;

use crocone\cart\Cart;

/**
 * Interface StorageInterface
 *
 * @package crocone\cart\storage
 */
interface StorageInterface
{
    /**
     * @param Cart $cart
     *
     * @return mixed
     */
    public function load(Cart $cart);

    /**
     * @param Cart $cart
     */
    public function save(Cart $cart);
}
