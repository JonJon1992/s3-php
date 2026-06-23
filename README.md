# S3 PHP Library

Uma biblioteca PHP simples e eficiente para trabalhar com Amazon S3, construída sobre o AWS SDK for PHP.

## Versão

**3.0.0** - Compatível com AWS SDK PHP ^3.350

## 🚀 Novidades na Versão 3.0.0

### ⚠️ Mudanças Importantes (Breaking Changes)
- **PHP 8.5+**: Versão mínima do PHP atualizada para 8.5
- **ACL não é mais `public-read` por padrão**: Uploads (`put`, `putFile`, `copy`, `uploadLargeFile`) não enviam mais o header ACL automaticamente. Isso garante compatibilidade com buckets que têm ACLs desabilitadas (Object Ownership = *Bucket owner enforced*, padrão na AWS atual). Para tornar objetos públicos, configure `'acl' => S3::ACL_PUBLIC_READ` ou use `setDefaultAcl()`.

### 🐛 Correções
- **`presignedPostUrl()`**: Reescrita usando `Aws\S3\PostObjectV4` (a implementação anterior usava um comando inexistente e não funcionava). Agora retorna `url`, `fields` e `attributes`.
- **`fileUrl()` depreciada**: Usava AWS Signature V2 (descontinuada). Agora delega para `presignedUrl()` (SigV4). Prefira `presignedUrl()`.
- **`getFiles()`**: Corrigido bug onde `str_replace` removia o prefixo de qualquer posição da chave (agora usa remoção ancorada no início).
- **`deleteBucket(force: true)`**: Agora pagina a listagem, suportando buckets com mais de 1000 objetos e tratando buckets vazios corretamente.
- **Cache de URL**: As chaves de cache agora consideram o `root()`, evitando retorno de URL incorreta ao alternar o prefixo raiz.

### ✨ Configuração de ACL

```php
// Objetos privados (padrão) — nenhum header ACL é enviado
s3_config('default', [
    'bucket' => 'meu-bucket',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
]);

// Objetos públicos — defina a ACL explicitamente
s3_config('publico', [
    'bucket' => 'meu-bucket',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
    'acl' => \S3\Lib\S3::ACL_PUBLIC_READ,
]);

// Ou em tempo de execução
s3('default')->setDefaultAcl(\S3\Lib\S3::ACL_PUBLIC_READ);

// Ou por chamada
s3('default')->put('arquivo.txt', 'conteúdo', ['ACL' => \S3\Lib\S3::ACL_PUBLIC_READ]);
```

## 🚀 Novidades na Versão 2.4.0

### ✨ Atualização para PHP 8.2+
- **PHP 8.2+**: Atualização da versão mínima do PHP para 8.2 para aproveitar recursos modernos
- **Melhor Performance**: Aproveitamento de otimizações do PHP 8.2+
- **Recursos Modernos**: Suporte a recursos mais recentes da linguagem
- **Compatibilidade Futura**: Preparação para versões futuras do PHP
- **Arrow Functions**: Uso de arrow functions para melhor performance
- **Array Functions**: Otimização de operações de array com `array_sum()` e `array_column()`

## 🚀 Novidades na Versão 2.3.0

### ✨ Correções de Compatibilidade
- **Métodos Deprecated Atualizados**: Substituição de `doesBucketExist()` e `doesObjectExist()` pelos métodos V2
- **PHP 8.1+**: Atualização da versão mínima do PHP para compatibilidade com AWS SDK mais recente
- **Melhor Compatibilidade**: Garantia de compatibilidade com versões futuras do AWS SDK

## 🚀 Novidades na Versão 2.2.0

### ✨ Principais Melhorias
- **Suporte a CDN**: Integração com CDNs externos para melhor performance
- **Cache de URLs**: Cache interno para URLs frequentemente acessadas
- **S3 Transfer Acceleration**: Suporte nativo para transferência acelerada
- **URLs de Download Especializadas**: URLs com attachment disposition
- **Upload Direto**: URLs pré-assinadas para upload direto ao S3
- **Validação Melhorada**: Validação e sanitização de URLs e configurações
- **Endpoints Dinâmicos**: Endpoints otimizados por região
- **Novas Helper Functions**: API mais simples e intuitiva

### 📊 Benefícios
- **Performance**: Cache de URLs e endpoints otimizados
- **Flexibilidade**: Suporte a CDN, acceleration e endpoints customizados
- **Segurança**: Validação e sanitização de URLs
- **Usabilidade**: Helper functions simplificadas

## Requisitos

- PHP 8.5 ou superior
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

### Configuração com CDN

```php
s3_config('cdn', [
    'bucket' => 'meu-bucket',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
    'url_cdn' => 'https://cdn.exemplo.com' // CDN opcional
]);
```

### Configuração com S3 Transfer Acceleration

```php
s3_config('accelerated', [
    'bucket' => 'meu-bucket-acelerado',
    'region' => 'us-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
    'use_accelerate' => true
]);
```

### Configuração para Desenvolvimento Local (LocalStack)

```php
s3_config('local', [
    'bucket' => 'meu-bucket-local',
    'region' => 'us-east-1',
    'access_key' => 'test',
    'secret_key' => 'test',
    'endpoint' => 'http://localhost:4566',
    'use_path_style' => true
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

### URLs e Links Melhorados

```php
// URL pública do objeto (com CDN se configurado)
$url = $bucket->getObjectUrl('uploads/imagem.jpg');

// URL direta (sem CDN)
$directUrl = $bucket->getObjectUrl('uploads/imagem.jpg', true, false);

// URL de download com attachment disposition
$downloadUrl = $bucket->getDownloadUrl('documento.pdf', 3600, [
    'response_content_disposition' => 'attachment; filename="documento.pdf"',
    'response_content_type' => 'application/pdf'
]);

// URL com assinatura (expira em 1 hora)
$signedUrl = $bucket->fileUrl('uploads/imagem.jpg', 3600);

// URL pré-assinada (recomendado)
$presignedUrl = $bucket->presignedUrl('uploads/imagem.jpg', 3600);

// URL pré-assinada com opções customizadas
$presignedUrlWithOptions = $bucket->presignedUrl('uploads/imagem.jpg', 3600, 'GET', [
    'ResponseContentType' => 'image/jpeg',
    'ResponseContentDisposition' => 'inline; filename="imagem.jpg"'
]);

// URL para upload direto (POST)
$postData = $bucket->presignedPostUrl('uploads/novo-arquivo.txt', 3600, [
    ['content-length-range', 1, 10485760], // 1 byte a 10MB
    ['starts-with', '$key', 'uploads/']
]);

// Helper functions
$url = s3_url('uploads/imagem.jpg'); // Com CDN
$directUrl = s3_direct_url('uploads/imagem.jpg'); // Sem CDN
$cdnUrl = s3_cdn_url('uploads/imagem.jpg'); // Força CDN
$downloadUrl = s3_download_url('documento.pdf', 3600); // Download
$presignedUrl = s3_presigned_url('uploads/imagem.jpg', 3600); // Pré-assinada
$postData = s3_presigned_post('uploads/arquivo.txt', 3600); // Upload direto
```

### Gerenciamento de Cache

```php
// Limpar cache de URLs
$bucket->clearUrlCache();
s3_clear_cache();

// Verificar configurações
echo "Usando CDN: " . ($bucket->isUsingCdn() ? 'Sim' : 'Não') . "\n";
echo "Usando Acceleration: " . ($bucket->isUsingAccelerate() ? 'Sim' : 'Não') . "\n";
echo "Usando Path Style: " . ($bucket->isUsingPathStyle() ? 'Sim' : 'Não') . "\n";

// Obter configuração do endpoint
$endpointConfig = $bucket->getEndpointConfig();
echo "Tipo de endpoint: " . $endpointConfig['type'] . "\n";
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

// Estatísticas em uma única varredura (tamanho + contagem juntos)
$stats = $bucket->getBucketStats(); // ['size' => int, 'count' => int]
```

### Operações Concorrentes (CommandPool)

Para envio/download de muitos objetos, use as operações concorrentes — elas
disparam várias requisições em paralelo via `Aws\CommandPool`, reduzindo o
tempo total comparado a chamadas sequenciais:

```php
// Upload concorrente (5 simultâneos por padrão)
$result = $bucket->uploadMultiple([
    'uploads/a.txt' => 'conteúdo A',
    'uploads/b.txt' => fopen('/path/local.bin', 'r'), // string ou resource
], $metadata = [], $concurrency = 10);

// $result = ['results' => [chave => Aws\Result], 'errors' => [chave => Throwable]]
echo count($result['results']) . " enviados, " . count($result['errors']) . " falhas\n";

// Download concorrente
$out = $bucket->downloadMultiple(['uploads/a.txt', 'uploads/b.txt'], 10);
// $out = ['results' => [chave => conteúdo], 'errors' => [chave => Throwable]]

// Helpers
$result = s3_upload_multiple(['uploads/a.txt' => 'A'], [], 10);
$out    = s3_download_multiple(['uploads/a.txt'], 10);
$stats  = s3_bucket_stats();
```

> **Nota sobre deletes:** `deleteMultiple()` já usa o `DeleteObjects` em lote do
> S3 (até 1000 chaves por requisição), que é mais eficiente que `CommandPool`
> para exclusões. A partir da 3.0.0 ele divide automaticamente listas maiores
> que 1000 em lotes e retorna um `array<int, Aws\Result>` (um por lote).

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
    'secret_key' => 'PROD_SECRET_KEY',
    'url_cdn' => 'https://cdn-prod.exemplo.com'
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

### Funções Básicas
- `s3(?string $alias = null): Bucket` - Obter instância do bucket
- `s3_config(string $alias, array $config): S3` - Configurar bucket
- `s3_exists(string $file_path, ?string $alias = null): bool` - Verificar se arquivo existe

### Funções de URL
- `s3_url(string $file_path, ?string $alias = null, bool $https = true, bool $useCdn = true): string` - Obter URL do objeto
- `s3_direct_url(string $file_path, ?string $alias = null, bool $https = true): string` - Obter URL direta (sem CDN)
- `s3_cdn_url(string $file_path, ?string $alias = null): string` - Obter URL usando CDN
- `s3_download_url(string $file_path, int $expiration = 3600, array $options = [], ?string $alias = null): string` - Obter URL de download
- `s3_presigned_url(string $file_path, int $expiration = 3600, string $method = 'GET', array $options = [], ?string $alias = null): string` - Obter URL pré-assinada
- `s3_presigned_post(string $file_path, int $expiration = 3600, array $conditions = [], ?string $alias = null): array` - Obter URL para upload direto

### Funções de Gerenciamento
- `s3_set_cdn(string $cdnUrl, ?string $alias = null): Bucket` - Configurar CDN
- `s3_clear_cache(?string $alias = null): Bucket` - Limpar cache de URLs

### Funções de Operações
- `s3_upload(string $file_path, $content, array $metadata = [], ?string $alias = null): \Aws\Result` - Upload de conteúdo
- `s3_upload_file(string $file_path, string $local_path, array $metadata = [], ?string $alias = null): \Aws\Result` - Upload de arquivo local
- `s3_delete(string $file_path, ?string $alias = null): \Aws\Result` - Deletar arquivo
- `s3_get(string $file_path, ?string $alias = null): ?string` - Obter conteúdo
- `s3_metadata(string $file_path, ?string $alias = null): ?array` - Obter metadados
- `s3_upload_multiple(array $files, array $metadata = [], int $concurrency = 5, ?string $alias = null): array` - Upload concorrente
- `s3_download_multiple(array $keys, int $concurrency = 5, ?string $alias = null): array` - Download concorrente
- `s3_bucket_stats(?string $alias = null): array` - Tamanho + contagem em uma varredura

## Exemplos Práticos

### Upload Direto com Formulário HTML

```php
// Gerar URL para upload direto
$postData = s3_presigned_post('uploads/arquivo.txt', 3600, [
    ['content-length-range', 1, 10485760] // 1 byte a 10MB
]);

// Formulário HTML
?>
<form action="<?php echo $postData['url']; ?>" method="post" enctype="multipart/form-data">
    <?php foreach ($postData['fields'] as $key => $value): ?>
        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
    <?php endforeach; ?>
    <input type="file" name="file" required>
    <input type="submit" value="Upload">
</form>
```

### Download com Nome Personalizado

```php
// Gerar URL de download com nome personalizado
$downloadUrl = s3_download_url('documentos/relatorio.pdf', 3600, [
    'response_content_disposition' => 'attachment; filename="relatorio-mensal.pdf"',
    'response_content_type' => 'application/pdf'
]);

// Redirecionar para download
header('Location: ' . $downloadUrl);
exit;
```

### Cache de URLs para Performance

```php
// URLs são automaticamente cacheadas
$url1 = s3_url('imagem.jpg'); // Primeira chamada - gera URL
$url2 = s3_url('imagem.jpg'); // Segunda chamada - usa cache

// Limpar cache quando necessário
s3_clear_cache();
```

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

## Melhorias na Versão 2.2.0

### ✅ Novas Funcionalidades
- **Suporte a CDN**: Integração com CDNs externos
- **Cache de URLs**: Cache interno para melhor performance
- **S3 Transfer Acceleration**: Suporte nativo para transferência acelerada
- **URLs de Download Especializadas**: URLs com attachment disposition
- **Upload Direto**: URLs pré-assinadas para upload direto ao S3
- **Endpoints Dinâmicos**: Endpoints otimizados por região
- **Validação Melhorada**: Validação e sanitização de URLs e configurações

### ✅ Novas Helper Functions
- `s3_download_url()` - URLs de download especializadas
- `s3_cdn_url()` - URLs usando CDN
- `s3_direct_url()` - URLs diretas sem CDN
- `s3_set_cdn()` - Configurar CDN
- `s3_clear_cache()` - Limpar cache de URLs
- `s3_presigned_post()` - URLs para upload direto
- `s3_upload()` - Upload de conteúdo
- `s3_upload_file()` - Upload de arquivo local
- `s3_delete()` - Deletar arquivo
- `s3_get()` - Obter conteúdo
- `s3_metadata()` - Obter metadados

### ✅ Melhorias de Performance
- Cache de URLs para requisições repetidas
- Endpoints específicos por região
- S3 Transfer Acceleration para melhor latência
- Validação e sanitização otimizadas

### ✅ Melhorias de Segurança
- Validação de URLs geradas
- Sanitização de parâmetros
- Validação de configurações de bucket e região

### ✅ Melhorias de Usabilidade
- API mais intuitiva com helper functions
- Configuração flexível para diferentes cenários
- Documentação completa com exemplos

## Compatibilidade

- **AWS SDK PHP**: ^3.350
- **PHP**: ^8.5
- **Regiões**: Todas as regiões AWS
- **LocalStack**: Suporte completo
- **CDNs**: CloudFront, Cloudflare, etc.
- **S3 Transfer Acceleration**: Suporte nativo

## Licença

Este projeto está sob a licença MIT. 