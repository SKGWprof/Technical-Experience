
#include <stdio.h>
#include <cs50.h>
#include <ctype.h>
#include <string.h>
#include <stdlib.h>

int main(int argc, string argv[])
{
    if (argc == 1 || argc > 2)
    {
        printf("Input only one key. \n");
        exit(1);
    }
    else if (strlen(argv[1]) != 26)
    {
        printf("Key is not 26 letters.\n");
        exit(1);
    }
    else
    {
        for (int i = 0; argv[1][i] != '\0'; i++)
        {
            if (isalpha(argv[1][i]) != 0)
            {}
            else
            {
            printf("Key is not alphabetical.\n");
            exit(1);
            }
            for (int x = 1; x <= i; x++)
            {
                if (tolower(argv[1][i]) == tolower(argv[1][i - x]))
                {
                    printf("duplicated letters in character.\n");
                    exit(1);
                }
                else
                {}
            }
        }

    }
    string alpha = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    string p = get_string("plaintext: ");
    for (int i = 0; i < strlen(p); i++)
    {
        if (islower(p[i]) != 0 && isalpha(p[i]) != 0)
        {
            for (int n = 0; n < 26; n++)
            {
                if (tolower(alpha[n]) == p[i])
                {
                    p[i] = tolower(argv[1][n]);
                    break;
                }
                else
                {}
            }
        }
        if (isupper(p[i]) != 0 && isalpha(p[i]) != 0)
        {
            for (int n = 0; n < 26; n++)
            {
                if (alpha[n] == p[i])
                {
                    p[i] = toupper(argv[1][n]);
                    break;
                }
                else
                {}
            }
        }
        else
        {}
    }
    printf("ciphertext: %s\n", p);
}
