# PHP + OpenTelemetry ゼロコード計装例

## 環境構築

```shell
$ make
```

## PHP コードからトレースデータを送信

http://localhost:8000/

## Jaeger で確認

http://localhost:16686/

## Usage

```shell
$ make
```

- Application: http://localhost:8000/trace.php
- Grafana: http://localhost:3000
  - user: admin / pass: admin
