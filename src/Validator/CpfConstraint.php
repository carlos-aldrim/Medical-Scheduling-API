<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[\Attribute]
class CpfConstraint extends Constraint
{
    public string $message = 'The CPF "{{ value }}" is not valid.';
}

class CpfConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CpfConstraint) {
            throw new UnexpectedTypeException($constraint, CpfConstraint::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $cpf = preg_replace('/\D/', '', $value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            $this->addViolation($value, $constraint);
            return;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += $cpf[$i] * ($t + 1 - $i);
            }
            $remainder = (10 * $sum) % 11 % 10;
            if ($cpf[$t] != $remainder) {
                $this->addViolation($value, $constraint);
                return;
            }
        }
    }

    private function addViolation(mixed $value, CpfConstraint $constraint): void
    {
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $value)
            ->addViolation();
    }
}
