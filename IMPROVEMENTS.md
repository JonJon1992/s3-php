# Melhorias na Geração de URLs para Download - S3 PHP Library

## Resumo das Melhorias

Este documento descreve as melhorias implementadas na biblioteca S3 PHP para otimizar a geração de URLs para download e melhorar a flexibilidade e performance geral.

## 🚀 Principais Melhorias

### 1. **Configuração Dinâmica de Endpoints**

#### Antes:
```php
// Endpoint hardcoded para sa-east-1
'endpoint' => 'https://s3.sa-east-1.amazonaws.com'
```

#### Depois:
```php
// Endpoints específicos por região para melhor performance
protected function getDefaultEndpoint(): string
{
    $endpoints = [
        'us-east-1' => 'https://s3.amazonaws.com',
        'us-east-2' => 'https://s3.us-east-2.amazonaws.com',
        'us-west-1' => 'https://s3.us-west-1.amazonaws.com',
        'us-west-2' => 'https://s3.us-west-2.amazonaws.com',
        'sa-east-1' => 'https://s3.sa-east-1.amazonaws.com',
        'eu-west-1' => 'https://s3.eu-west-1.amazonaws.com',
        'eu-central-1' => 'https://s3.eu-central-1.amazonaws.com',
        'ap-southeast-1' => 'https://s3.ap-southeast-1.amazonaws.com',
        'ap-southeast-2' => 'https://s3.ap-southeast-2.amazonaws.com',
        'ap-northeast-1' => 'https://s3.ap-northeast-1.amazonaws.com',
    ];

    return $endpoints[$this->region] ?? "https://s3.{$this->region}.amazonaws.com";
}
```

### 2. **Suporte a CDN**

#### Configuração:
```php
s3_config('default', [
    'bucket' => 'meu-bucket',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
    'url_cdn' => 'https://cdn.exemplo.com' // CDN opcional
]);
```

#### Uso:
```php
// URL com CDN
$url = $bucket->getObjectUrl('arquivo.txt', true, true);

// URL sem CDN
$directUrl = $bucket->getObjectUrl('arquivo.txt', true, false);

// Helper functions
$cdnUrl = s3_cdn_url('arquivo.txt');
$directUrl = s3_direct_url('arquivo.txt');
```

### 3. **S3 Transfer Acceleration**

#### Configuração:
```php
s3_config('accelerated', [
    'bucket' => 'meu-bucket-acelerado',
    'region' => 'us-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
    'use_accelerate' => true
]);
```

### 4. **Cache de URLs**

#### Implementação:
```php
protected array $urlCache = [];

public function getObjectUrl(string $file_path, bool $https = true, bool $useCdn = true): string
{
    $cacheKey = "url_{$file_path}_{$https}_{$useCdn}";
    
    if (isset($this->urlCache[$cacheKey])) {
        return $this->urlCache[$cacheKey];
    }

    // ... geração da URL ...
    
    // Cache da URL
    $this->urlCache[$cacheKey] = $url;
    
    return $url;
}
```

#### Gerenciamento de Cache:
```php
// Limpar cache
$bucket->clearUrlCache();
s3_clear_cache();
```

### 5. **URLs de Download Especializadas**

#### Nova funcionalidade:
```php
public function getDownloadUrl(string $file_path, int $expiration = 3600, array $options = []): string
{
    $defaultOptions = [
        'response_content_disposition' => 'attachment',
        'response_content_type' => 'application/octet-stream',
        'response_cache_control' => 'no-cache'
    ];

    $options = array_merge($defaultOptions, $options);
    
    $cmd = $this->client->getCommand('GetObject', array_merge([
        'Bucket' => $this->bucket,
        'Key' => $this->path($file_path)
    ], $options));

    $request = $this->client->createPresignedRequest($cmd, "+{$expiration} seconds");
    return (string) $request->getUri();
}
```

#### Uso:
```php
// URL de download com attachment disposition
$downloadUrl = $bucket->getDownloadUrl('arquivo.pdf', 3600, [
    'response_content_disposition' => 'attachment; filename="documento.pdf"',
    'response_content_type' => 'application/pdf'
]);

// Helper function
$downloadUrl = s3_download_url('arquivo.pdf', 3600, [
    'response_content_disposition' => 'attachment; filename="download.pdf"'
]);
```

### 6. **URLs Pré-assinadas Melhoradas**

#### Com opções customizadas:
```php
public function presignedUrl(string $file_path, int $exp = 3600, string $method = 'GET', array $options = []): string
{
    $params = [
        'Bucket' => $this->bucket,
        'Key' => $this->path($file_path)
    ];

    // Adicionar opções customizadas
    if (!empty($options)) {
        $params = array_merge($params, $options);
    }

    $cmd = $this->client->getCommand($method, $params);
    $request = $this->client->createPresignedRequest($cmd, "+{$exp} seconds");
    return (string) $request->getUri();
}
```

#### Uso:
```php
// URL pré-assinada com opções
$presignedUrl = $bucket->presignedUrl('arquivo.txt', 3600, 'GET', [
    'ResponseContentType' => 'text/plain',
    'ResponseContentDisposition' => 'inline; filename="arquivo.txt"'
]);

// Helper function
$presignedUrl = s3_presigned_url('arquivo.txt', 3600, 'GET', [
    'ResponseContentType' => 'text/plain'
]);
```

### 7. **URLs para Upload Direto (POST)**

#### Nova funcionalidade:
```php
public function presignedPostUrl(string $file_path, int $exp = 3600, array $conditions = []): array
{
    $params = [
        'Bucket' => $this->bucket,
        'Key' => $this->path($file_path),
        'Expires' => time() + $exp
    ];

    if (!empty($conditions)) {
        $params['Conditions'] = $conditions;
    }

    $cmd = $this->client->getCommand('PostObject', $params);
    $request = $this->client->createPresignedRequest($cmd, "+{$exp} seconds");
    
    return [
        'url' => (string) $request->getUri(),
        'fields' => $request->getBody()->getContents()
    ];
}
```

#### Uso:
```php
// URL para upload direto
$postData = $bucket->presignedPostUrl('uploads/arquivo.txt', 3600, [
    ['content-length-range', 1, 10485760], // 1 byte a 10MB
    ['starts-with', '$key', 'uploads/']
]);

// Helper function
$postData = s3_presigned_post('uploads/novo-arquivo.txt', 1800);
```

### 8. **Validação e Sanitização de URLs**

#### Implementação:
```php
protected function sanitizeUrl(string $url): string
{
    // Remover caracteres inválidos
    $url = filter_var($url, FILTER_SANITIZE_URL);
    
    // Validar URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException("Invalid URL generated: {$url}");
    }
    
    return $url;
}
```

### 9. **Validação de Configuração Melhorada**

#### Antes:
```php
// Validação básica
if (!isset($config[$field]) || empty($config[$field])) {
    throw new InvalidArgumentException("Missing required configuration: {$field}");
}
```

#### Depois:
```php
// Validação completa
protected function validateConfig(array $config): void
{
    $required = ['bucket', 'region', 'access_key', 'secret_key'];
    foreach ($required as $field) {
        if (!isset($config[$field]) || empty($config[$field])) {
            throw new InvalidArgumentException("Missing required configuration: {$field}");
        }
    }

    // Validar formato do bucket
    if (!preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $config['bucket'])) {
        throw new InvalidArgumentException("Invalid bucket name format");
    }

    // Validar região
    if (!preg_match('/^[a-z0-9-]+$/', $config['region'])) {
        throw new InvalidArgumentException("Invalid region format");
    }
}
```

### 10. **Novas Helper Functions**

#### Funções adicionadas:
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

## 📊 Benefícios das Melhorias

### Performance:
- **Cache de URLs**: Reduz tempo de geração de URLs frequentemente acessadas
- **Endpoints otimizados**: URLs específicas por região para melhor latência
- **S3 Transfer Acceleration**: Melhora performance para uploads/downloads globais

### Flexibilidade:
- **Suporte a CDN**: Integração com CDNs externos
- **Múltiplos tipos de endpoint**: Local, acelerado, customizado
- **Opções customizadas**: Controle granular sobre URLs pré-assinadas

### Segurança:
- **Validação de URLs**: Previne URLs malformadas
- **Sanitização**: Remove caracteres perigosos
- **Validação de configuração**: Verifica formatos corretos

### Usabilidade:
- **Helper functions**: API mais simples e intuitiva
- **Configuração flexível**: Suporte a diferentes cenários
- **Documentação melhorada**: Exemplos claros e completos

## 🔧 Configurações Disponíveis

### Configuração Básica:
```php
s3_config('default', [
    'bucket' => 'meu-bucket',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY'
]);
```

### Com CDN:
```php
s3_config('cdn', [
    'bucket' => 'meu-bucket',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
    'url_cdn' => 'https://cdn.exemplo.com'
]);
```

### Com S3 Transfer Acceleration:
```php
s3_config('accelerated', [
    'bucket' => 'meu-bucket',
    'region' => 'us-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
    'use_accelerate' => true
]);
```

### Para Desenvolvimento Local:
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

## 🚀 Exemplos de Uso

### URLs de Download:
```php
// URL simples
$url = s3_url('documento.pdf');

// URL de download com attachment
$downloadUrl = s3_download_url('documento.pdf', 3600, [
    'response_content_disposition' => 'attachment; filename="documento.pdf"'
]);

// URL pré-assinada
$presignedUrl = s3_presigned_url('documento.pdf', 1800);
```

### Upload Direto:
```php
// Gerar URL para upload direto
$postData = s3_presigned_post('uploads/arquivo.txt', 3600, [
    ['content-length-range', 1, 10485760]
]);

// Usar em formulário HTML
echo '<form action="' . $postData['url'] . '" method="post" enctype="multipart/form-data">';
foreach ($postData['fields'] as $key => $value) {
    echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
}
echo '<input type="file" name="file">';
echo '<input type="submit" value="Upload">';
echo '</form>';
```

### Gerenciamento de Cache:
```php
// Limpar cache quando necessário
s3_clear_cache();

// Verificar configurações
$bucket = s3();
echo "Usando CDN: " . ($bucket->isUsingCdn() ? 'Sim' : 'Não') . "\n";
echo "Usando Acceleration: " . ($bucket->isUsingAccelerate() ? 'Sim' : 'Não') . "\n";
```

## 📝 Notas de Migração

### Compatibilidade:
- Todas as funções existentes mantêm compatibilidade
- Novos parâmetros são opcionais
- Comportamento padrão não foi alterado

### Recomendações:
1. **Migre gradualmente**: Use as novas funcionalidades conforme necessário
2. **Configure CDN**: Adicione suporte a CDN para melhor performance
3. **Use cache**: Aproveite o cache de URLs para aplicações com muitas requisições
4. **Valide configurações**: Use as novas validações para detectar problemas cedo

## 🔮 Próximos Passos

### Funcionalidades Futuras:
- **Cache distribuído**: Suporte a Redis/Memcached para cache de URLs
- **Métricas**: Coleta de métricas de performance
- **Rate limiting**: Controle de taxa de requisições
- **Retry logic**: Lógica de retry automático
- **Async operations**: Operações assíncronas para melhor performance

### Otimizações:
- **Compression**: Suporte a compressão automática
- **Image processing**: Processamento automático de imagens
- **Batch operations**: Operações em lote para múltiplos arquivos
- **Webhook support**: Suporte a webhooks para eventos S3 