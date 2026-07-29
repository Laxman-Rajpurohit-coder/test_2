pipeline {
    agent any

    stages {

        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Install PHP Dependencies') {
            steps {
                bat 'composer install --no-interaction --prefer-dist --optimize-autoloader'
            }
        }

        stage('Install & Build Frontend') {
            steps {
                // Assumes .env already exists in this project folder — since
                // Jenkins runs on the same machine you already develop on,
                // there's no separate secrets-injection needed. If .env is
                // missing, this build will fail here — that's intentional,
                // better than building with wrong/blank values.
                bat 'if not exist .env (echo MISSING .env FILE - ABORTING & exit /b 1)'
                bat 'npm ci'
                bat 'npm run build'
                // IMPORTANT: this order matters. .env must exist BEFORE
                // npm run build, because Vite bakes VITE_REVERB_* values in
                // at build time, not runtime — the exact bug we found and
                // fixed earlier in this project.
            }
        }

        stage('Run Test Suite') {
            steps {
                bat 'docker compose exec -T laravel.test php artisan config:clear'
                bat 'docker compose exec -T laravel.test php artisan test'
                // Pipeline stops here automatically if any test fails,
                // including FlowTenantIsolationTest and
                // AiBotPipelineIntegrationTest — nothing below this
                // runs on a broken build.
            }
        }

        stage('Rebuild & Restart Local Containers') {
            steps {
                bat 'docker compose up -d --build'
                bat 'docker compose exec -T laravel.test php artisan migrate --force'
                bat 'docker compose exec -T laravel.test php artisan config:cache'
                bat 'docker compose exec -T laravel.test php artisan route:cache'
            }
        }

        stage('Health Check') {
            steps {
                bat 'docker compose ps'
            }
        }
    }

    post {
        failure {
            echo 'Build or deploy failed — check the stage logs above. Containers were not touched if Run Test Suite failed.'
        }
        success {
            echo 'Rebuilt and restarted successfully. Confirm ngrok is still forwarding to port 80, and send yourself a real WhatsApp message to verify end-to-end.'
        }
    }
}
