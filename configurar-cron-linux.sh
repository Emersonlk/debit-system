#!/bin/bash

# Script para configurar cron do Laravel Schedule Runner
# Execute: chmod +x configurar-cron-linux.sh && ./configurar-cron-linux.sh

echo "🔧 Configurando Cron para Laravel Schedule Runner"
echo ""

# Obter caminho atual do projeto
PROJECT_PATH=$(pwd)
PHP_PATH=$(which php)

echo "📁 Caminho do projeto: $PROJECT_PATH"
echo "🐘 Caminho do PHP: $PHP_PATH"
echo ""

# Verificar se o caminho está correto
if [ ! -f "$PROJECT_PATH/artisan" ]; then
    echo "❌ Erro: artisan não encontrado em $PROJECT_PATH"
    echo "Execute este script na raiz do projeto Laravel"
    exit 1
fi

# Criar linha do cron
CRON_LINE="* * * * * cd $PROJECT_PATH && $PHP_PATH artisan schedule:run >> /dev/null 2>&1"

echo "📝 Linha do cron que será adicionada:"
echo "$CRON_LINE"
echo ""

# Verificar se já existe
if crontab -l 2>/dev/null | grep -q "artisan schedule:run"; then
    echo "⚠️  Já existe uma entrada para 'artisan schedule:run' no crontab"
    echo ""
    read -p "Deseja substituir? (s/n): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        # Remove linha antiga e adiciona nova
        (crontab -l 2>/dev/null | grep -v "artisan schedule:run"; echo "$CRON_LINE") | crontab -
        echo "✅ Cron atualizado com sucesso!"
    else
        echo "❌ Operação cancelada"
        exit 0
    fi
else
    # Adiciona nova linha
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
    echo "✅ Cron configurado com sucesso!"
fi

echo ""
echo "📋 Crontab atual:"
crontab -l | grep "artisan schedule:run" || echo "(nenhuma entrada encontrada)"

echo ""
echo "✅ Configuração concluída!"
echo ""
echo "🧪 Para testar:"
echo "   php artisan schedule:run"
echo ""
echo "📊 Para ver tarefas agendadas:"
echo "   php artisan schedule:list"
