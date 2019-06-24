pipeline {
    agent any
    options {
        disableConcurrentBuilds()
        timeout(time: 1, unit: 'HOURS')
    }
    stages {
        stage('Prepare') {
            steps {
                 sh 'printenv'
                 telegramSend "Pipeline STARTED: `${env.JOB_NAME}`\n\nAuthor: `${env.GIT_AUTHOR_NAME} <${env.GIT_AUTHOR_EMAIL}>`\nBuild Number: ${env.BUILD_NUMBER}\n\n${env.RUN_DISPLAY_URL}"
                 sh 'make prepare'
            }
        }
        stage('Build Marshall') {
            parallel {
                stage('Marshall') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-marshall'
                    }
                }
                stage('Marshall') {
                    agent { node { label 'x86' } }
                    when {  branch 'master' }
                    steps {
                         sh 'make build-marshall'
                         sh 'make push-marshall'
                    }
                }
            }
        }
        stage('Build PHP Runtime Containers'){
            parallel {
                stage('PHP 5.6'){
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-core-5.6'
                         sh 'make push-core-5.6'
                    }
                }
                stage('PHP 5.6'){
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-core-5.6'
                         sh 'make push-core-5.6'
                    }
                }
                stage('PHP 7.0'){
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-core-7.0'
                    }
                }
                stage('PHP 7.0'){
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-core-7.0'
                         sh 'make push-core-7.0'
                    }
                }
                stage('PHP 7.1'){
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-core-7.1'
                    }
                }
                stage('PHP 7.1'){
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-core-7.1'
                         sh 'make push-core-7.1'
                    }
                }
                stage('PHP 7.2'){
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-core-7.2'
                    }
                }
                stage('PHP 7.2'){
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-core-7.2'
                         sh 'make push-core-7.2'
                    }
                }
                stage('PHP 7.3'){
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-core-7.3'
                    }
                }
                stage('PHP 7.3'){
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-core-7.3'
                         sh 'make push-core-7.3'
                    }
                }
                stage('Node 8'){
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-node-8'
                         sh 'make tag-node-8'
                    }
                }
                stage('Node 8'){
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-node-8'
                         sh 'make tag-node-8'
                         sh 'make push-node-8'
                    }
                }
                stage('Node 10'){
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-node-10'
                         sh 'make tag-node-10'
                    }
                }
                stage('Node 10'){
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-node-10'
                         sh 'make tag-node-10'
                         sh 'make push-node-10'
                    }
                }
                stage('Node 11'){
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-node-11'
                         sh 'make tag-node-11'
                    }
                }
                stage('Node 11'){
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-node-11'
                         sh 'make tag-node-11'
                         sh 'make push-node-11'
                    }
                }
                stage('Node 12'){
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-node-12'
                         sh 'make tag-node-12'
                    }
                }
                stage('Node 12'){
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-node-12'
                         sh 'make tag-node-12'
                         sh 'make push-node-12'
                    }
                }
            }
        }
        stage('Build Derived PHP Containers'){
            parallel {
                stage('CLI PHP 5.6') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-cli-5.6'
                    }
                }
                stage('CLI PHP 5.6') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-cli-5.6'
                         sh 'make push-cli-5.6'
                    }
                }
                stage('Nginx PHP 5.6') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-nginx-5.6'
                    }
                }
                stage('Nginx PHP 5.6') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-nginx-5.6'
                         sh 'make push-nginx-5.6'
                    }
                }
                stage('Apache PHP 5.6') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-apache-5.6'
                    }
                }
                stage('Apache PHP 5.6') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-apache-5.6'
                         sh 'make push-apache-5.6'
                    }
                }

                stage('CLI PHP 7.0') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-cli-7.0'
                    }
                }
                stage('CLI PHP 7.0') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-cli-7.0'
                         sh 'make push-cli-7.0'
                    }
                }
                stage('Nginx PHP 7.0') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-nginx-7.0'
                    }
                }
                stage('Nginx PHP 7.0') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-nginx-7.0'
                         sh 'make push-nginx-7.0'
                    }
                }
                stage('Apache PHP 7.0') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-apache-7.0'
                    }
                }
                stage('Apache PHP 7.0') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-apache-7.0'
                         sh 'make push-apache-7.0'
                    }
                }

                stage('CLI PHP 7.1') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-cli-7.1'
                    }
                }
                stage('CLI PHP 7.1') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-cli-7.1'
                         sh 'make push-cli-7.1'
                    }
                }
                stage('Nginx PHP 7.1') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-nginx-7.1'
                    }
                }
                stage('Nginx PHP 7.1') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-nginx-7.1'
                         sh 'make push-nginx-7.1'
                    }
                }
                stage('Apache PHP 7.1') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-apache-7.1'
                    }
                }
                stage('Apache PHP 7.1') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-apache-7.1'
                         sh 'make push-apache-7.1'
                    }
                }

                stage('CLI PHP 7.2') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-cli-7.2'
                    }
                }
                stage('CLI PHP 7.2') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-cli-7.2'
                         sh 'make push-cli-7.2'
                    }
                }
                stage('Nginx PHP 7.2') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-nginx-7.2'
                    }
                }
                stage('Nginx PHP 7.2') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-nginx-7.2'
                         sh 'make push-nginx-7.2'
                    }
                }
                stage('Apache PHP 7.2') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-apache-7.2'
                    }
                }
                stage('Apache PHP 7.2') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-apache-7.2'
                         sh 'make push-apache-7.2'
                    }
                }

                stage('CLI PHP 7.3') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-cli-7.3'
                    }
                }
                stage('CLI PHP 7.3') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-cli-7.3'
                         sh 'make push-cli-7.3'
                    }
                }
                stage('Nginx PHP 7.3') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-nginx-7.3'
                    }
                }
                stage('Nginx PHP 7.3') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-nginx-7.3'
                         sh 'make push-nginx-7.3'
                    }
                }
                stage('Apache PHP 7.3') {
                    agent { node { label 'x86' } }
                    when { not { branch 'master' } }
                    steps {
                         sh 'make build-php-apache-7.3'
                    }
                }
                stage('Apache PHP 7.3') {
                    agent { node { label 'x86' } }
                    when { branch 'master' }
                    steps {
                         sh 'make build-php-apache-7.3'
                         sh 'make push-apache-7.3'
                    }
                }
            }
        }

        stage('Test Containers'){
            parallel {
                stage('All Nginx Containers'){
                    agent { node { label 'x86' } }
                    steps {
                        sh 'make test-php-nginx-5.6'
                        sh 'make test-php-nginx-7.0'
                        sh 'make test-php-nginx-7.1'
                        sh 'make test-php-nginx-7.2'
                        sh 'make test-php-nginx-7.3'
                    }
                }
                stage('All Apache Containers'){
                    agent { node { label 'x86' } }
                    steps {
                        sh 'make test-php-apache-5.6'
                        sh 'make test-php-apache-7.0'
                        sh 'make test-php-apache-7.1'
                        sh 'make test-php-apache-7.2'
                        sh 'make test-php-apache-7.3'
                    }
                }
            }
        }

        stage('Aliases'){
            agent { node { label 'x86' } }
            when { branch 'master' }
            steps {
                 sh 'make aliases'
            }
        }
    }
    post {
        success{
            telegramSend "Pipeline SUCCESS: `${env.JOB_NAME}`\n\nAuthor: `${env.GIT_AUTHOR_NAME} <${env.GIT_AUTHOR_EMAIL}>`\nBuild Number: ${env.BUILD_NUMBER}\n\n${env.RUN_DISPLAY_URL}"
        }
        aborted{
            telegramSend "Pipeline ABORTED: `${env.JOB_NAME}`\n\nAuthor: `${env.GIT_AUTHOR_NAME} <${env.GIT_AUTHOR_EMAIL}>`\nBuild Number: ${env.BUILD_NUMBER}\n\n${env.RUN_DISPLAY_URL}"
        }
        failure{
            telegramSend "Pipeline FAIL: `${env.JOB_NAME}`\n\nAuthor: `${env.GIT_AUTHOR_NAME} <${env.GIT_AUTHOR_EMAIL}>`\nBuild Number: ${env.BUILD_NUMBER}\n\n${env.RUN_DISPLAY_URL}"
        }
        cleanup {
            sh 'make cleanup'
            cleanWs()
        }
    }
}
