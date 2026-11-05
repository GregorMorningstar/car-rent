<?php

namespace App\Exceptions;

use RuntimeException;

/** Messenger nie przyjął wiadomości albo nie ma ustawionych danych dostępowych. */
class MessengerException extends RuntimeException {}
