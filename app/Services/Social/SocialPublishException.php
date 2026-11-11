<?php

namespace App\Services\Social;

use RuntimeException;

/**
 * Publishing failed. The message is shown to the admin as is (Serbian, plus
 * the network's own error text when there is one).
 */
class SocialPublishException extends RuntimeException {}
