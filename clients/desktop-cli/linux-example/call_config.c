#include <stdio.h>

#include "../dist/libacbr_api_cli.h"

int main(int argc, char **argv) {
    if (argc != 2) {
        fprintf(stderr, "uso: %s caminho/chamada.ini\n", argv[0]);
        return 1;
    }

    char *result = AcbrApiRunConfig(argv[1]);
    if (result == NULL) {
        fprintf(stderr, "AcbrApiRunConfig retornou NULL\n");
        return 1;
    }

    puts(result);
    AcbrApiFree(result);

    return 0;
}
