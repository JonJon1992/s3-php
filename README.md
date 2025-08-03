# S3 PHP Library

Uma biblioteca PHP simples e eficiente para trabalhar com Amazon S3, construída sobre o AWS SDK for PHP.

## Versão

**2.1.0** - Compatível com AWS SDK PHP ^3.350

## Requisitos

- PHP 8.0 ou superior
- Composer
- Extensões PHP: `ext-json`, `ext-openssl`

## Instalação

```bash
composer require jonjon1992/s3-php
```

## Configuração

### Configuração Básica

```php
<?php

require 'vendor/autoload.php';

use S3\Lib\S3;

// Configurar bucket
S3::singleton()->use('default', [
    'bucket' => 'meu-bucket',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY'
]);

// Usar helper function
s3_config('default', [
    'bucket' => 'meu-bucket',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY'
]);
```

### Configuração para Desenvolvimento Local (LocalStack)

```php
s3_config('local', [
    'bucket' => 'meu-bucket-local',
    'region' => 'us-east-1',
    'access_key' => 'test',
    'secret_key' => 'test',
    'endpoint' => 'http://localhost:4566'
]);
```

## Uso

### Operações Básicas

```php
// Obter instância do bucket
$bucket = s3('default');

// Upload de arquivo
$result = $bucket->putFile('uploads/imagem.jpg', '/path/to/local/image.jpg');

// Upload de conteúdo
$result = $bucket->put('documents/arquivo.txt', 'Conteúdo do arquivo');

// Download de arquivo
$content = $bucket->get('documents/arquivo.txt');

// Verificar se arquivo existe
if ($bucket->existObject('documents/arquivo.txt')) {
    echo "Arquivo existe!";
}

// Deletar arquivo
$result = $bucket->delete('documents/arquivo.txt');
```

### URLs e Links

```php
// URL pública do objeto
$url = $bucket->getObjectUrl('uploads/imagem.jpg');

// URL com assinatura (expira em 1 hora)
$signedUrl = $bucket->fileUrl('uploads/imagem.jpg', 3600);

// URL pré-assinada (recomendado)
$presignedUrl = $bucket->presignedUrl('uploads/imagem.jpg', 3600);

// Helper functions
$url = s3_url('uploads/imagem.jpg');
$presignedUrl = s3_presigned_url('uploads/imagem.jpg', 3600);
```

### Operações Avançadas

```php
// Upload de arquivo grande (multipart)
$result = $bucket->uploadLargeFile('videos/video.mp4', '/path/to/video.mp4');

// Upload com stream
$result = $bucket->putFileStream('uploads/imagem.jpg', '/path/to/image.jpg');

// Copiar arquivo
$result = $bucket->copy('uploads/old.jpg', 'uploads/new.jpg');

// Mover arquivo
$result = $bucket->move('uploads/temp.jpg', 'uploads/final.jpg');

// Deletar múltiplos arquivos
$result = $bucket->deleteMultiple([
    'uploads/file1.jpg',
    'uploads/file2.jpg',
    'uploads/file3.jpg'
]);

// Listar arquivos
$files = $bucket->getFiles('uploads/');
$files = $bucket->getFiles('uploads/', false); // Não recursivo

// Obter metadados do objeto
$metadata = $bucket->getObjectMetadata('uploads/imagem.jpg');

// Definir ACL do objeto
$bucket->setObjectAcl('uploads/imagem.jpg', S3::ACL_PRIVATE);

// Obter estatísticas do bucket
$size = $bucket->getBucketSize();
$count = $bucket->getBucketObjectCount();
```

### Gerenciamento de Buckets

```php
// Verificar se bucket existe
if ($bucket->isExistBucket()) {
    echo "Bucket existe!";
}

// Criar bucket
$result = $bucket->createBucket(S3::ACL_PRIVATE);

// Deletar bucket (com força para deletar objetos)
$result = $bucket->deleteBucket(true);

// Obter localização do bucket
$location = $bucket->locationBucket();
```

### Configuração de Metadados Padrão

```php
// Definir metadados padrão para todos os uploads
$bucket->setDefaultMetadata([
    'Content-Type' => 'application/octet-stream',
    'Cache-Control' => 'max-age=3600'
]);
```

### Múltiplos Buckets

```php
// Configurar múltiplos buckets
s3_config('prod', [
    'bucket' => 'bucket-producao',
    'region' => 'sa-east-1',
    'access_key' => 'PROD_ACCESS_KEY',
    'secret_key' => 'PROD_SECRET_KEY'
]);

s3_config('dev', [
    'bucket' => 'bucket-desenvolvimento',
    'region' => 'us-east-1',
    'access_key' => 'DEV_ACCESS_KEY',
    'secret_key' => 'DEV_SECRET_KEY'
]);

// Usar buckets específicos
$prodBucket = s3('prod');
$devBucket = s3('dev');
```

## Constantes Disponíveis

### ACLs (Access Control Lists)
- `S3::ACL_PRIVATE` - Privado
- `S3::ACL_PUBLIC_READ` - Leitura pública
- `S3::ACL_PUBLIC_WRITE` - Escrita pública
- `S3::ACL_AUTH_READ` - Leitura autenticada
- `S3::ACL_BUCKET_OWNER_READ` - Leitura do proprietário do bucket
- `S3::ACL_BUCKET_OWNER_FULL_CONTROL` - Controle total do proprietário

### Headers
- `S3::HEADER_CONTENT_TYPE` - Content-Type
- `S3::HEADER_REQUEST_PAYER` - Request Payer
- `S3::HEADER_ACL` - ACL Header

## Helper Functions

### Funções Disponíveis

- `s3(?string $alias = null): Bucket` - Obter instância do bucket
- `s3_config(string $alias, array $config): S3` - Configurar bucket
- `s3_exists(string $file_path, ?string $alias = null): bool` - Verificar se arquivo existe
- `s3_url(string $file_path, ?string $alias = null, bool $https = true): string` - Obter URL do objeto
- `s3_presigned_url(string $file_path, int $expiration = 3600, string $method = 'GET', ?string $alias = null): string` - Obter URL pré-assinada

## Tratamento de Erros

A biblioteca inclui tratamento robusto de erros:

```php
try {
    $content = $bucket->get('arquivo-inexistente.txt');
    if ($content === null) {
        echo "Arquivo não encontrado";
    }
} catch (S3Exception $e) {
    echo "Erro S3: " . $e->getMessage();
} catch (InvalidArgumentException $e) {
    echo "Erro de configuração: " . $e->getMessage();
}
```

## Melhorias na Versão 2.1.0

### ✅ Correções de Bugs
- Corrigido erro de tipagem em métodos
- Melhorado tratamento de exceções
- Corrigido problema com paths de arquivos
- Removido uso de `S3MultiRegionClient` desnecessário

### ✅ Novas Funcionalidades
- Upload de arquivos grandes com `MultipartUploader`
- URLs pré-assinadas nativas do AWS SDK
- Operações de cópia e movimentação de arquivos
- Deletar múltiplos arquivos de uma vez
- Obter metadados de objetos
- Gerenciamento de ACLs
- Estatísticas do bucket (tamanho e contagem)
- Configuração de metadados padrão
- Suporte a desenvolvimento local (LocalStack)

### ✅ Melhorias de Performance
- Otimização do autoloader do Composer
- Remoção de serviços AWS não utilizados
- Timeouts configuráveis
- Paginação melhorada para listagem de arquivos

### ✅ Melhorias de Código
- Tipagem forte em todos os métodos
- Documentação PHPDoc completa
- Validação de configurações
- Helper functions adicionais
- Melhor organização do código

## Compatibilidade

- **AWS SDK PHP**: ^3.350
- **PHP**: ^8.0
- **Regiões**: Todas as regiões AWS
- **LocalStack**: Suporte completo

## Licença

Este projeto está sob a licença MIT. 