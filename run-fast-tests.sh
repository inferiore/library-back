#!/bin/bash

echo "🚀 Ejecutando tests unitarios rápidos (sin base de datos)..."

# Tests que no requieren base de datos
docker-compose exec app vendor/bin/phpunit \
  tests/Unit/SimpleTest.php \
  tests/Unit/PureValidatorTest.php \
  tests/Unit/ValidatorSimpleTest.php \
  tests/Unit/Business/Validators/UserValidatorMockedTest.php \
  --no-coverage

echo "✅ Tests rápidos completados"