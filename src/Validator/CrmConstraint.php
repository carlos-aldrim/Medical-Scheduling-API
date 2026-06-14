<?php

namespace App\Validator;

use App\ValueObject\Crm;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class CrmConstraint extends Constraint
{
    public string $message = 'The CRM "{{ value }}" is not valid. Expected formats: 12345, CRM12345, CRM-SP-12345.';
}

class CrmConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CrmConstraint) {
            throw new UnexpectedTypeException($constraint, CrmConstraint::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!Crm::isValid((string) $value)) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
