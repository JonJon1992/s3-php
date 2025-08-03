# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2024-12-19

### Added
- Upload de arquivos grandes com `MultipartUploader`
- URLs pré-assinadas nativas do AWS SDK
- Operações de cópia e movimentação de arquivos
- Deletar múltiplos arquivos de uma vez
- Obter metadados de objetos
- Gerenciamento de ACLs
- Estatísticas do bucket (tamanho e contagem)
- Configuração de metadados padrão
- Suporte a desenvolvimento local (LocalStack)
- Helper functions adicionais (`s3_exists`, `s3_url`, `s3_presigned_url`)
- Documentação completa com exemplos
- Testes unitários abrangentes
- Configuração de qualidade de código (PHPStan, PHPCS, Psalm, Infection)
- Docker support para desenvolvimento
- Makefile para automação de tarefas

### Changed
- Melhorado tratamento de exceções
- Otimização do autoloader do Composer
- Remoção de serviços AWS não utilizados
- Tipagem forte em todos os métodos
- Validação de configurações aprimorada
- Melhor organização do código

### Fixed
- Corrigido erro de tipagem em métodos
- Corrigido problema com paths de arquivos
- Removido uso de `S3MultiRegionClient` desnecessário
- Melhorado tratamento de erros

### Technical
- Compatível com AWS SDK PHP ^3.350
- Requer PHP ^8.0
- Timeouts configuráveis
- Paginação melhorada para listagem de arquivos

## [2.0.0] - 2024-XX-XX

### Added
- Suporte ao AWS SDK PHP v3
- Métodos básicos de CRUD para S3
- Configuração de múltiplos buckets
- Helper functions básicas

### Changed
- Migração do AWS SDK PHP v2 para v3
- Refatoração completa da API

## [1.0.10] - 2024-XX-XX

### Fixed
- Correções de bugs menores
- Melhorias de compatibilidade

## [1.0.9] - 2024-XX-XX

### Fixed
- Correções de bugs menores

## [1.0.8] - 2024-XX-XX

### Fixed
- Correções de bugs menores

## [1.0.7] - 2024-XX-XX

### Fixed
- Correções de bugs menores

## [1.0.6] - 2024-XX-XX

### Fixed
- Correções de bugs menores

## [1.0.5] - 2024-XX-XX

### Fixed
- Correções de bugs menores

## [1.0.4] - 2024-XX-XX

### Fixed
- Correções de bugs menores

## [1.0.3] - 2024-XX-XX

### Added
- Primeira versão estável
- Funcionalidades básicas do S3

### Fixed
- Correções de bugs iniciais

## [1.0.2] - 2024-XX-XX

### Fixed
- Correções de bugs iniciais

## [1.0.1] - 2024-XX-XX

### Fixed
- Correções de bugs iniciais

## [1.0] - 2024-XX-XX

### Added
- Versão inicial da biblioteca
- Funcionalidades básicas do S3 