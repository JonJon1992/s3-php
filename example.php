<?php

require 'vendor/autoload.php';

use S3\Lib\S3;

// Configuração do bucket
s3_config('default', [
    'bucket' => 'meu-bucket-exemplo',
    'region' => 'sa-east-1',
    'access_key' => 'SUA_ACCESS_KEY',
    'secret_key' => 'SUA_SECRET_KEY'
]);

// Configuração para desenvolvimento local (LocalStack)
s3_config('local', [
    'bucket' => 'meu-bucket-local',
    'region' => 'us-east-1',
    'access_key' => 'test',
    'secret_key' => 'test',
    'endpoint' => 'http://localhost:4566'
]);

try {
    // Obter instância do bucket
    $bucket = s3('default');
    
    echo "=== Exemplo de Uso S3 PHP Library ===\n\n";
    
    // 1. Verificar se bucket existe
    if ($bucket->isExistBucket()) {
        echo "✅ Bucket existe\n";
    } else {
        echo "❌ Bucket não existe\n";
        // Criar bucket se não existir
        $bucket->createBucket(S3::ACL_PRIVATE);
        echo "✅ Bucket criado\n";
    }
    
    // 2. Upload de arquivo
    $testContent = "Este é um arquivo de teste criado em " . date('Y-m-d H:i:s');
    $result = $bucket->put('teste/arquivo.txt', $testContent, [
        'Content-Type' => 'text/plain',
        'Cache-Control' => 'max-age=3600'
    ]);
    echo "✅ Arquivo enviado: {$result['Key']}\n";
    
    // 3. Verificar se arquivo existe
    if ($bucket->existObject('teste/arquivo.txt')) {
        echo "✅ Arquivo existe no S3\n";
    }
    
    // 4. Download de arquivo
    $content = $bucket->get('teste/arquivo.txt');
    echo "📄 Conteúdo do arquivo: {$content}\n";
    
    // 5. Obter URL do objeto
    $url = $bucket->getObjectUrl('teste/arquivo.txt');
    echo "🔗 URL do objeto: {$url}\n";
    
    // 6. Gerar URL pré-assinada (expira em 1 hora)
    $presignedUrl = $bucket->presignedUrl('teste/arquivo.txt', 3600);
    echo "🔐 URL pré-assinada: {$presignedUrl}\n";
    
    // 7. Copiar arquivo
    $bucket->copy('teste/arquivo.txt', 'teste/arquivo-copia.txt');
    echo "📋 Arquivo copiado\n";
    
    // 8. Listar arquivos
    $files = $bucket->getFiles('teste/');
    echo "📁 Arquivos na pasta teste/: " . implode(', ', $files) . "\n";
    
    // 9. Obter metadados do objeto
    $metadata = $bucket->getObjectMetadata('teste/arquivo.txt');
    echo "📊 Tamanho do arquivo: " . ($metadata['ContentLength'] ?? 'N/A') . " bytes\n";
    echo "📅 Última modificação: " . ($metadata['LastModified'] ?? 'N/A') . "\n";
    
    // 10. Configurar metadados padrão
    $bucket->setDefaultMetadata([
        'Content-Type' => 'application/octet-stream',
        'Cache-Control' => 'max-age=86400'
    ]);
    echo "⚙️ Metadados padrão configurados\n";
    
    // 11. Upload de arquivo com metadados padrão
    $bucket->put('teste/arquivo-com-metadados.txt', 'Conteúdo com metadados padrão');
    echo "📤 Arquivo com metadados padrão enviado\n";
    
    // 12. Obter estatísticas do bucket
    $size = $bucket->getBucketSize();
    $count = $bucket->getBucketObjectCount();
    echo "📈 Estatísticas do bucket:\n";
    echo "   - Total de objetos: {$count}\n";
    echo "   - Tamanho total: " . number_format($size) . " bytes\n";
    
    // 13. Deletar arquivos de teste
    $bucket->deleteMultiple([
        'teste/arquivo.txt',
        'teste/arquivo-copia.txt',
        'teste/arquivo-com-metadados.txt'
    ]);
    echo "🗑️ Arquivos de teste deletados\n";
    
    echo "\n=== Exemplo concluído com sucesso! ===\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "📋 Stack trace: " . $e->getTraceAsString() . "\n";
}

// Exemplo de uso com helper functions
echo "\n=== Usando Helper Functions ===\n";

// Verificar se arquivo existe usando helper
if (s3_exists('teste/arquivo.txt')) {
    echo "✅ Arquivo existe (helper function)\n";
} else {
    echo "❌ Arquivo não existe (helper function)\n";
}

// Obter URL usando helper
$url = s3_url('teste/arquivo.txt');
echo "🔗 URL via helper: {$url}\n";

// Gerar URL pré-assinada usando helper
$presignedUrl = s3_presigned_url('teste/arquivo.txt', 1800, 'GET'); // 30 minutos
echo "🔐 URL pré-assinada via helper: {$presignedUrl}\n";

echo "\n=== Helper Functions testadas! ===\n"; 