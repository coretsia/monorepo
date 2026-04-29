<!--
  Coretsia Framework (Monorepo)

  Project: Coretsia Framework (Monorepo)
  Authors: Vladyslav Mudrichenko and contributors
  Copyright (c) 2026 Vladyslav Mudrichenko

  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
  SPDX-License-Identifier: Apache-2.0

  For contributors list, see git history.
  See LICENSE and NOTICE in the project root for full license information.
-->

## 🚀 Додаток A: Ops (Non-SSoT, поза DAG фреймворку, Non-product doc)

> Розділ A.* **не є частиною dependency-safe roadmap фреймворку** і не блокує релізи фаз. Це ops-документація, яка може
> жити окремо в `docs/ops/*`. У SSoT залишаємо лише точки інтеграції: `/health`, `/metrics`, артефакти, build outputs.

### A.1 Infrastructure as Code (IaC) Templates

Що робимо:
- [ ] **Terraform модулі** для основних хмарних провайдерів:
  - [ ] AWS: ECS Fargate, RDS, ElastiCache, ALB
  - [ ] GCP: Cloud Run, Cloud SQL, Memorystore, Load Balancer
  - [ ] Azure: Container Instances, Azure SQL, Redis, Application Gateway

- [ ] **Docker Compose** для локального розгортання та staging:
  - [ ] `docker-compose.prod.yml` з повним стеком (app, db, redis, queue)
  - [ ] Health check конфігурації
  - [ ] Resource limits та scaling налаштування

- [ ] **Kubernetes manifests**:
  - [ ] Helm chart для Coretsia додатків
  - [ ] Deployment, Service, Ingress, ConfigMap, Secret templates
  - [ ] Horizontal Pod Autoscaler налаштування
  - [ ] PodDisruptionBudget для zero-downtime деплоїв

Тести:
- [ ] IaC validation: `terraform validate`, `helm lint`
- [ ] Integration: деплой на staging environment
- [ ] Smoke tests після деплою

DoD:
- [ ] Один командний деплой: `make deploy-staging`
- [ ] Можна розгорнути production-ready стек за 15 хвилин

Документація:
- [ ] `docs/guides/deployment.md`: інструкції для кожного провайдера
- [ ] `docs/guides/deployment.md`: покроковий гайд

---

### A.2 CI/CD Pipeline (GitHub Actions/GitLab CI)

Що робимо:
- [ ] **Multi-stage pipeline**:
  - [ ] Test stage: unit, integration, contract, security scanning
  - [ ] Build stage: Docker image build, артефакти
  - [ ] Deploy stage: автоматичний деплой на staging
  - [ ] Production stage: manual approval → канарийний → повний деплой

- [ ] **Канарийні деплої**:
  - [ ] Traffic splitting (5% → 25% → 50% → 100%)
  - [ ] Автоматичне відкатування при аномаліях
  - [ ] Метрики для моніторингу: error rate, latency, throughput

- [ ] **Security scanning**:
  - [ ] SAST (Static Application Security Testing)
  - [ ] SCA (Software Composition Analysis)
  - [ ] Container vulnerability scanning
  - [ ] Secrets detection

Тести:
- [ ] Pipeline success на тестових репозиторіях
- [ ] Rollback процедури (симуляція failure)
- [ ] Security scanning виявляє вразливості

DoD:
- [ ] Повністю автоматизований пайплайн від commit до production
- [ ] Security scanning не пропускає критичні вразливості

Документація:
- [ ] `.github/workflows/README.md`
- [ ] `docs/guides/ci-cd.md`

---

### A.3 Zero-Downtime Deployment Strategies

Що робимо:
- [ ] **Blue-Green деплой**:
  - [ ] Terraform модулі для створення паралельного environment
  - [ ] Traffic switching через load balancer
  - [ ] Старе environment зберігається для швидкого rollback
- [ ] **Database migrations**:
  - [ ] Backward compatible міграції

### A.4 HTTP/2 + HTTP/3 (Ops guide)

SSoT:
- HTTP/2/HTTP/3 — транспортний рівень, налаштовується в reverse proxy/runtime.
- Coretsia залишається PSR-7/15 runtime, без залежності від конкретного протоколу.

Deliverables:
- `docs/ops/http2-http3.md` (див. 3.240.0)

DoD:
- Dev/ops має один документ з working recipes (Caddy/Nginx/Envoy).
