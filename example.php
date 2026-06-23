<?php

require 'vendor/autoload.php';

use S3\Lib\S3;

// Configuração do bucket principal
s3_config('default', [
    'bucket' => 'meu-bucket-exemplo',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
    'url_cdn' => 'https://cdn.exemplo.com', // CDN opcional
    'use_accelerate' => false // S3 Transfer Acceleration
]);

// Configuração para desenvolvimento local (LocalStack)
s3_config('local', [
    'bucket' => 'meu-bucket-local',
    'region' => 'us-east-1',
    'access_key' => 'test',
    'secret_key' => 'test',
    'endpoint' => 'http://localhost:4566',
    'use_path_style' => true
]);

// Configuração com S3 Transfer Acceleration
s3_config('accelerated', [
    'bucket' => 'meu-bucket-acelerado',
    'region' => 'us-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY',
    'use_accelerate' => true
]);

try {
    // Obter instância do bucket
    $bucket = s3('default');

    echo "=== Exemplo de Uso S3 PHP Library Melhorada ===\n\n";

    // 1. Verificar se bucket existe
    if ($bucket->isExistBucket()) {
        echo "✅ Bucket existe\n";
    } else {
        echo "❌ Bucket não existe\n";
        // Criar bucket se não existir
        $bucket->createBucket(S3::ACL_PRIVATE);
        echo "✅ Bucket criado\n";
    }

    // 2. Configurar CDN (se disponível)
    if (!empty($bucket->getCdnUrl())) {
        echo "🌐 CDN configurado: {$bucket->getCdnUrl()}\n";
    }

    // 3. Upload de arquivo
    $testContent = "Este é um arquivo de teste criado em " . date('Y-m-d H:i:s');
    $result = $bucket->put('teste/arquivo.txt', $testContent, [
        'Content-Type' => 'text/plain',
        'Cache-Control' => 'max-age=3600'
    ]);
    echo "✅ Arquivo enviado: {$result['Key']}\n";

    // 4. Verificar se arquivo existe
    if ($bucket->existObject('teste/arquivo.txt')) {
        echo "✅ Arquivo existe no S3\n";
    }

    // 5. Download de arquivo
    $content = $bucket->get('teste/arquivo.txt');
    echo "📄 Conteúdo do arquivo: {$content}\n";

    // 6. Obter URL do objeto (com CDN se configurado)
    $url = $bucket->getObjectUrl('teste/arquivo.txt');
    echo "🔗 URL do objeto (com CDN): {$url}\n";

    // 7. Obter URL direta (sem CDN)
    $directUrl = $bucket->getObjectUrl('teste/arquivo.txt', true, false);
    echo "🔗 URL direta (sem CDN): {$directUrl}\n";

    // 8. Gerar URL de download (com attachment disposition)
    $downloadUrl = $bucket->getDownloadUrl('teste/arquivo.txt', 3600, [
        'response_content_disposition' => 'attachment; filename="arquivo.txt"',
        'response_content_type' => 'text/plain'
    ]);
    echo "📥 URL de download: {$downloadUrl}\n";

    // 9. Gerar URL pré-assinada (expira em 1 hora)
    $presignedUrl = $bucket->presignedUrl('teste/arquivo.txt', 3600);
    echo "🔐 URL pré-assinada: {$presignedUrl}\n";

    // 10. Gerar URL pré-assinada com opções customizadas
    $presignedUrlWithOptions = $bucket->presignedUrl('teste/arquivo.txt', 3600, 'GET', [
        'ResponseContentType' => 'text/plain',
        'ResponseContentDisposition' => 'inline; filename="arquivo.txt"'
    ]);
    echo "🔐 URL pré-assinada com opções: {$presignedUrlWithOptions}\n";

    // 11. Gerar URL para upload direto (POST)
    $postData = $bucket->presignedPostUrl('uploads/arquivo.txt', 3600, [
        ['content-length-range', 1, 10485760], // 1 byte a 10MB
        ['starts-with', '$key', 'uploads/']
    ]);
    echo "📤 URL para upload direto: {$postData['url']}\n";
    echo "📋 Campos do formulário: " . json_encode($postData['fields']) . "\n";

    // 12. Copiar arquivo
    $bucket->copy('teste/arquivo.txt', 'teste/arquivo-copia.txt');
    echo "📋 Arquivo copiado\n";

    // 13. Listar arquivos
    $files = $bucket->getFiles('teste/');
    echo "📁 Arquivos na pasta teste/: " . implode(', ', $files) . "\n";

    // 14. Obter metadados do objeto
    $metadata = $bucket->getObjectMetadata('teste/arquivo.txt');
    echo "📊 Tamanho do arquivo: " . ($metadata['ContentLength'] ?? 'N/A') . " bytes\n";
    echo "📅 Última modificação: " . ($metadata['LastModified'] ?? 'N/A') . "\n";

    // 15. Configurar metadados padrão
    $bucket->setDefaultMetadata([
        'Content-Type' => 'application/octet-stream',
        'Cache-Control' => 'max-age=86400'
    ]);
    echo "⚙️ Metadados padrão configurados\n";

    // 16. Upload de arquivo com metadados padrão
    $bucket->put('teste/arquivo-com-metadados.txt', 'Conteúdo com metadados padrão');
    echo "📤 Arquivo com metadados padrão enviado\n";

    // 17. Obter estatísticas do bucket
    $size = $bucket->getBucketSize();
    $count = $bucket->getBucketObjectCount();
    echo "📈 Estatísticas do bucket:\n";
    echo "   - Total de objetos: {$count}\n";
    echo "   - Tamanho total: " . number_format($size) . " bytes\n";

    // 18. Informações de configuração do endpoint
    $endpointConfig = $bucket->getEndpointConfig();
    echo "🔧 Configuração do endpoint:\n";
    echo "   - Tipo: {$endpointConfig['type']}\n";
    echo "   - URL: {$endpointConfig['url']}\n";
    echo "   - Usando CDN: " . ($bucket->isUsingCdn() ? 'Sim' : 'Não') . "\n";
    echo "   - Usando Acceleration: " . ($bucket->isUsingAccelerate() ? 'Sim' : 'Não') . "\n";
    echo "   - Usando Path Style: " . ($bucket->isUsingPathStyle() ? 'Sim' : 'Não') . "\n";

    // 19. Limpar cache de URLs
    $bucket->clearUrlCache();
    echo "🧹 Cache de URLs limpo\n";

    // 20. Operações concorrentes (CommandPool)
    $upload = $bucket->uploadMultiple([
        'teste/lote-1.txt' => 'conteúdo 1',
        'teste/lote-2.txt' => 'conteúdo 2',
    ], [], 5);
    echo "⚡ Uploads concorrentes: " . count($upload['results']) . " ok, "
        . count($upload['errors']) . " erros\n";

    $download = $bucket->downloadMultiple(['teste/lote-1.txt', 'teste/lote-2.txt'], 5);
    echo "⚡ Downloads concorrentes: " . count($download['results']) . " arquivos\n";

    // 21. Estatísticas do bucket (uma única varredura)
    $stats = $bucket->getBucketStats();
    echo "📊 Bucket: {$stats['count']} objetos, {$stats['size']} bytes\n";

    // 22. Deletar arquivos de teste
    $bucket->deleteMultiple([
        'teste/arquivo.txt',
        'teste/arquivo-copia.txt',
        'teste/arquivo-com-metadados.txt',
        'teste/lote-1.txt',
        'teste/lote-2.txt'
    ]);
    echo "🗑️ Arquivos de teste deletados\n";

    echo "\n=== Exemplo concluído com sucesso! ===\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "📋 Stack trace: " . $e->getTraceAsString() . "\n";
}

// Exemplo de uso com helper functions melhoradas
echo "\n=== Usando Helper Functions Melhoradas ===\n";

// Verificar se arquivo existe usando helper
if (s3_exists('teste/arquivo.txt')) {
    echo "✅ Arquivo existe (helper function)\n";
} else {
    echo "❌ Arquivo não existe (helper function)\n";
}

// Obter URL usando helper (com CDN)
$url = s3_url('teste/arquivo.txt');
echo "🔗 URL via helper (com CDN): {$url}\n";

// Obter URL direta (sem CDN)
$directUrl = s3_direct_url('teste/arquivo.txt');
echo "🔗 URL direta via helper: {$directUrl}\n";

// Obter URL de CDN específica
$cdnUrl = s3_cdn_url('teste/arquivo.txt');
echo "🌐 URL CDN via helper: {$cdnUrl}\n";

// Gerar URL de download
$downloadUrl = s3_download_url('teste/arquivo.txt', 1800, [
    'response_content_disposition' => 'attachment; filename="download.txt"'
]);
echo "📥 URL de download via helper: {$downloadUrl}\n";

// Gerar URL pré-assinada usando helper
$presignedUrl = s3_presigned_url('teste/arquivo.txt', 1800, 'GET', [
    'ResponseContentType' => 'text/plain'
]);
echo "🔐 URL pré-assinada via helper: {$presignedUrl}\n";

// Gerar URL para upload direto
$postData = s3_presigned_post('uploads/novo-arquivo.txt', 1800);
echo "📤 URL para upload direto via helper: {$postData['url']}\n";

// Configurar CDN via helper
s3_set_cdn('https://novo-cdn.exemplo.com');
echo "🌐 Novo CDN configurado via helper\n";

// Limpar cache via helper
s3_clear_cache();
echo "🧹 Cache limpo via helper\n";

// Upload via helper
s3_upload('teste/helper-upload.txt', 'Conteúdo via helper function');
echo "📤 Upload via helper function\n";

// Obter conteúdo via helper
$content = s3_get('teste/helper-upload.txt');
echo "📄 Conteúdo via helper: {$content}\n";

// Obter metadados via helper
$metadata = s3_metadata('teste/helper-upload.txt');
echo "📊 Metadados via helper: " . json_encode($metadata) . "\n";

// Deletar via helper
s3_delete('teste/helper-upload.txt');
echo "🗑️ Arquivo deletado via helper\n";

echo "\n=== Helper Functions testadas! ===\n";

// Exemplo de uso com diferentes configurações
echo "\n=== Testando Diferentes Configurações ===\n";

// Testar bucket local
try {
    $localBucket = s3('local');
    echo "🏠 Bucket local configurado: " . $localBucket->getBucket() . "\n";
    echo "🔧 Endpoint local: " . $localBucket->endpoint() . "\n";
} catch (Exception $e) {
    echo "❌ Erro no bucket local: " . $e->getMessage() . "\n";
}

// Testar bucket acelerado
try {
    $acceleratedBucket = s3('accelerated');
    echo "⚡ Bucket acelerado configurado: " . $acceleratedBucket->getBucket() . "\n";
    echo "🔧 Endpoint acelerado: " . $acceleratedBucket->endpoint() . "\n";
} catch (Exception $e) {
    echo "❌ Erro no bucket acelerado: " . $e->getMessage() . "\n";
}

echo "\n=== Todos os testes concluídos! ===\n";
