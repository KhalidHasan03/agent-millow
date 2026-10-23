<?php

declare(strict_types=1);

namespace App\Services\Tools\Validation;

use JsonSchema\Constraints\Factory as ConstraintFactory;
use JsonSchema\Validator as JsonSchemaValidator;

final class ToolInputValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $input
     * @return array{valid: bool, errors: array<string, array<string>>}
     */
    public function validate(array $schema, array $input): array
    {
        if (empty($schema)) {
            return ['valid' => true, 'errors' => []];
        }

        $validator = new JsonSchemaValidator(new ConstraintFactory);

        $data = json_decode(json_encode($input, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $schemaObject = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);

        $validator->validate($data, $schemaObject);

        if ($validator->isValid()) {
            return ['valid' => true, 'errors' => []];
        }

        $errors = [];
        foreach ($validator->getErrors() as $error) {
            $pointer = $error['property'] !== '' ? ltrim($error['property'], '.') : 'input';
            $errors[$pointer][] = $error['message'];
        }

        return ['valid' => false, 'errors' => $errors];
    }
}
