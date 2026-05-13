# laravel-ai-act-compliance

AI Act compliance bundle for Laravel AI applications.

## Modules
- Disclosure middleware
- Risk register
- DSAR
- Bias monitoring
- Human review tracker
- Incident manager
- Consent
- Cybersecurity
- Compliance attestation

## Install
```bash
composer require padosoft/laravel-ai-act-compliance
```

## Config
```bash
php artisan vendor:publish --tag=ai-act-compliance-config
php artisan vendor:publish --tag=ai-act-compliance-migrations
```

## Contracts
- `Padosoft\\AiActCompliance\\DSAR\\Contracts\\UserDataExporter`
- `Padosoft\\AiActCompliance\\DSAR\\Contracts\\UserDataDeleter`
- `Padosoft\\AiActCompliance\\BiasMonitoring\\Contracts\\CohortParityMetric`
