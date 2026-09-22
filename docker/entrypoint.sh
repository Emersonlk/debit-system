#!/bin/sh
set -e

if [ -f "artisan" ]; then
    # --isolated: app e queue sobem juntos e ambos chegam aqui. O primeiro a pegar
    # o lock aplica as migrations; os demais recebem "already running" e seguem o
    # boot com exit 0, sem tentar migrar em paralelo.
    #
    # O lock usa o store de arquivo em vez do configurado: com CACHE_STORE=database
    # ele viveria em `cache_locks`, tabela criada pela própria migration — no
    # primeiro boot o comando falharia antes de migrar (erro 1146). O diretório do
    # store de arquivo fica dentro do projeto, que é bind mount compartilhado pelos
    # dois containers, então o lock vale entre eles.
    #
    # Sem `|| true` e sob `set -e`: uma migration que falha aborta o boot de
    # propósito. Subir a aplicação com o schema desatualizado apenas troca um erro
    # visível no boot por erros 500 imprevisíveis em runtime.
    CACHE_STORE=file php artisan migrate --force --isolated --no-interaction

    # O seed popula dados de demonstração e usuários de senha conhecida, então é
    # exclusivo do ambiente local e de um único container. As duas condições são
    # exigidas aqui; os próprios seeders repetem a checagem de ambiente, porque o
    # entrypoint não é o único caminho até `db:seed`.
    if [ "${RUN_SEED}" = "true" ] && [ "${APP_ENV}" = "local" ]; then
        # Ao contrário das migrations, uma falha aqui não derruba o boot: são dados
        # de conveniência, e um ambiente local sem eles ainda é utilizável. O erro
        # continua visível — o que não pode acontecer é ser descartado em silêncio.
        if ! php artisan db:seed --force --no-interaction; then
            echo "AVISO: db:seed falhou. O container segue sem os dados de demonstracao." >&2
        fi
    fi
fi

exec "$@"
