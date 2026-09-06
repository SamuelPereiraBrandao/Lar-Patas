````markdown
# Lar & Patas

O Lar & Patas é uma plataforma de adoção responsável de cães e gatos.

A aplicação permite visualizar pets disponíveis, realizar solicitações de adoção, acompanhar o processo, cadastrar pets da família, favoritar animais, interagir com a comunidade, trocar mensagens e receber notificações.

Também possui áreas administrativas para gerenciamento de pets, usuários, abrigos e solicitações.

## Tecnologias utilizadas

- Laravel 13
- PHP 8.3+
- MySQL
- Redis
- Laravel Horizon
- Vue 3
- Vuetify
- Pinia
- Vite
- Ably
- Sanctum

## Requisitos

Antes de iniciar, tenha instalado:

- PHP 8.3 ou superior
- Composer
- Node.js e npm
- MySQL ou MariaDB
- Redis
- Git

## Instalação

Clone o projeto:

```bash
git clone https://github.com/SamuelPereiraBrandao/Lar-Patas.git
cd Lar-Patas
````

Instale as dependências do backend:

```bash
composer install
```

Instale as dependências do frontend:

```bash
npm install
```

Crie o arquivo `.env`:

```bash
cp .env.example .env
```

No Windows:

```cmd
copy .env.example .env
```

Gere a chave da aplicação:

```bash
php artisan key:generate
```

Crie um banco de dados MySQL chamado:

```text
pet_adoption
```

Depois, configure as credenciais no arquivo `.env`.

Execute as migrations e os seeders:

```bash
php artisan migrate --seed
```

Crie o link para os arquivos públicos:

```bash
php artisan storage:link
```

## Configuração do `.env`

Altere principalmente estas variáveis:

```env
APP_NAME="Lar & Patas"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pet_adoption
DB_USERNAME=root
DB_PASSWORD=sua_senha

QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## Configuração do Ably

Para habilitar mensagens e notificações em tempo real, configure:

```env
ABLY_API_KEY=sua_chave_ably
ABLY_VERIFY_SSL=true
```

A chave do Ably deve estar no formato:

```text
keyName:secret
```

A variável `ABLY_API_KEY` deve ser mantida apenas no backend.

## Configuração de e-mail

Para desenvolvimento, os e-mails podem ser armazenados nos logs:

```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="Lar & Patas"
```

Para utilizar SMTP, altere para:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.seuprovedor.com
MAIL_PORT=587
MAIL_USERNAME=seu_usuario
MAIL_PASSWORD=sua_senha
MAIL_FROM_ADDRESS=seu_email@dominio.com
MAIL_FROM_NAME="Lar & Patas"
```

## Redis

O projeto possui um `compose.yaml` para iniciar o Redis com Docker:

```bash
docker compose up -d redis
```

Para verificar se o Redis está funcionando:

```bash
docker compose ps
```

## Executando a aplicação

Abra terminais separados e execute:

Terminal 1 — frontend:

```bash
npm run dev
```

Terminal 2 — servidor Laravel:

```bash
php artisan serve
```

Terminal 3 — filas e notificações:

```bash
php artisan horizon
```

Terminal 4 — scheduler:

```bash
php artisan schedule:work
```

A aplicação ficará disponível em:

```text
http://localhost:8000
```

## Comando alternativo

O projeto também possui um comando que inicia os principais processos de desenvolvimento:

```bash
composer run dev
```

Caso utilize esse comando, ainda será necessário garantir que o Redis esteja ativo.

## Seeders

O comando abaixo executa todos os seeders principais:

```bash
php artisan migrate --seed
```

Ele cria:

* estados e cidades;
* usuários de demonstração;
* pets;
* abrigos;
* adoções;
* publicações;
* amizades;
* conversas;
* mensagens;
* notificações.

Para recriar completamente o banco em ambiente local:

```bash
php artisan migrate:fresh --seed
```

> Esse comando apaga todas as tabelas do banco. Não execute em produção.

## Usuário de demonstração

Após executar os seeders:

```text
E-mail: admin@larepatas.test
Senha: password
```

As demais contas de demonstração também utilizam a senha:

```text
password
```
## Postman

A documentação customizada da API está disponível no Postman:

[Lar & Patas API — documentação Postman](https://documenter.getpostman.com/view/32790910/2sBYAxNoWP)

A documentação contém requisições organizadas por fluxo da aplicação:

- autenticação;
- pets;
- favoritos;
- saúde dos pets;
- adoções;
- perfil;
- comunidade;
- chat e mensagens;
- notificações;
- amizades;
- dashboards;
- administração.

Para testar localmente:

1. Inicie o Redis.
2. Execute o Laravel com `php artisan serve`.
3. Execute o frontend com `npm run dev`.
4. Execute o Horizon com `php artisan horizon`.
5. Configure a URL base como `http://localhost:8000`.
6. Execute primeiro o fluxo de autenticação.
7. Depois execute as requisições protegidas.

Usuário de demonstração:

```text
E-mail: admin@larepatas.test
Senha: password

## Testes

Para executar os testes:

```bash
php artisan test
```

## Comandos úteis

Limpar o cache da aplicação:

```bash
php artisan optimize:clear
```

Verificar as rotas:

```bash
php artisan route:list
```

Gerar os arquivos para produção:

```bash
npm run build
```

## Observações

* O Redis precisa estar ativo para o Horizon e as notificações funcionarem.
* O Horizon precisa estar em execução para processar filas, e-mails e eventos do Ably.
* O scheduler precisa estar ativo para os lembretes de adoção serem enviados.
* Nunca envie o arquivo `.env` para o repositório.
* Nunca exponha a chave do Ably no frontend.

````

E uma correção importante: o comando correto é:

```bash
php artisan migrate --seed
````

Não existe `migrate --seeder`.
