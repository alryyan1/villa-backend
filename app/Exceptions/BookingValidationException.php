<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by BookingService::createBooking() for any business-rule guard
 * failure (villa/guest missing contact info, contract inactive, no
 * availability, etc.). Controllers catch this and turn it into a 422 JSON
 * response using getMessage().
 */
class BookingValidationException extends Exception
{
}
