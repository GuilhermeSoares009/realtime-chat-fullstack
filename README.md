# Real-time Chat Application

Sistema de chat em tempo real com Laravel + Next.js + PostgreSQL + Laravel Reverb.

## Stack Tecnológica

### Backend
- Laravel 11
- PHP 8.3
- PostgreSQL/Sqlite
- Laravel Sanctum
- Laravel Reverb
- Redis
- Telescope

### Frontend
- Next.js 14
- React 18
- Tailwind CSS
- Laravel Echo
- Zustand

---

## Requisitos

- PHP 8.3+
- Composer
- Node.js 18+
- npm ou yarn
- PostgreSQL 16+ (ou SQLite para desenvolvimento)
- Redis
- Docker

---

## Rodando com Docker

### 1. Backend

```bash
# Subir todos os serviços (backend, frontend, DB, Redis)
docker-compose up -d

# Entrar no container do backend
docker-compose exec backend bash

# Rodar migrations
php artisan migrate

# Popular banco com seeders
php artisan db:seed
```

O backend estará disponível em http://localhost:8000.

### 2. Frontend
```bash
Copiar código
cd frontend

npm install

npm run dev
```

Frontend disponível em http://localhost:3000.

#### Features Implementadas
✅ Autenticação  
✅ Chat      
✅ Histórico de mensagens com paginação  
✅ Busca de usuários    
✅ Logging  
✅ Testes automatizados  
✅ Documentação Swagger  



## 3. Documentação da API
Após iniciar o backend, acesse:  
Swagger UI: http://localhost:8000/api/documentation  
Telescope: http://localhost:8000/telescope  