# @see https://github.com/amazeeio/lagoon/tree/master/images/php/fpm
ARG CLI_IMAGE
FROM ${CLI_IMAGE:-cli} as cli

FROM amazeeio/php:7.2-fpm-v1.1.2

COPY --from=cli /app /app
