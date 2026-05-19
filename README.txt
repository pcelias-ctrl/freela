ESCALA APP - PAINEL ADMINISTRATIVO PHP 7.2 + MYSQL

1) Suba a pasta no servidor Apache.
2) Crie o banco importando o arquivo: sql/database.sql
3) Edite config/db.php com usuário e senha do MySQL.
4) Acesse:
   /admin/login.php
   Login: admin@admin.com
   Senha: admin123

Links principais:
- Cadastro público do freelancer: /public/register.php
- Painel admin: /admin/login.php
- Login do restaurante: /restaurant/login.php

O que esta versão faz:
- Login do administrador
- Cadastro de restaurantes com login próprio
- Cadastro de funções
- Cadastro público de freelancer com foto
- Aprovação, bloqueio e recusa de freelancers
- Lançamento de vagas/escalas por admin
- Lançamento de vagas/escalas pelo restaurante
- Controle de vagas preenchidas já preparado pela tabela shift_applications

Observações:
- PHP 7.2 funciona com este projeto.
- Use HTTPS em produção.
- Troque a senha inicial do administrador.
- Dê permissão de escrita na pasta uploads/freelancers.
