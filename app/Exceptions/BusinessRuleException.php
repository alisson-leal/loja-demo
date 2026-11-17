<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Violação de regra de negócio. A mensagem é segura para exibir ao usuário.
 */
class BusinessRuleException extends RuntimeException {}
