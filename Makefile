.PHONY: help install test test-coverage cs cs-fix stan rector infection psalm quality fix clean

help: ## Mostra esta ajuda
	@echo "Comandos disponíveis:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Instala as dependências
	composer install

update: ## Atualiza as dependências
	composer update

test: ## Executa os testes
	composer test

test-coverage: ## Executa os testes com cobertura
	composer test-coverage

cs: ## Executa o PHP CodeSniffer
	composer cs

cs-fix: ## Corrige automaticamente problemas de código
	composer cs-fix

stan: ## Executa o PHPStan
	composer stan

rector: ## Executa o Rector para refatoração
	composer rector

infection: ## Executa o Infection (teste de mutação)
	composer infection

psalm: ## Executa o Psalm
	composer psalm

quality: ## Executa todas as verificações de qualidade
	composer quality

fix: ## Corrige automaticamente problemas de código
	composer fix

clean: ## Limpa arquivos temporários
	rm -rf coverage/
	rm -rf .phpunit.cache/
	rm -rf .psalm/
	rm -rf .infection/
	rm -rf .phpstan/
	find . -name "*.log" -delete

validate: ## Valida o composer.json
	composer validate --strict

security: ## Verifica vulnerabilidades de segurança
	composer audit

example: ## Executa o exemplo
	php example.php

all: clean install quality ## Executa tudo: limpa, instala e verifica qualidade 