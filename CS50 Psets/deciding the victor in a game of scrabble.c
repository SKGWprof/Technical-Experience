#include <stdio.h>
#include <cs50.h>
#include <ctype.h>

int check(string s, int *sum);

int main(void)
{
    string s = get_string("Player 1: ");
    string p = get_string("Player 2: ");
    int sum1 = 0;
    int sum2 = 0;
    check(s, &sum1);
    check(p, &sum2);
    if (sum1 > sum2)
    {
        printf("Player 1 wins!\n");
    }
    else if (sum2 > sum1)
    {
        printf("Player 2 wins!\n");
    }
    else
    {
        printf("Tie\n");
    }
}

int check(string s, int *sum)
{
    for (int i = 0; s[i] != '\0'; i++)
    {
        int n = toupper(s[i]);
        if (n == 'A' || n == 'E' || n == 'I' || n == 'N' || n == 'L' || n == 'A' || n == 'O' || n == 'R' || n == 'S' || n == 'T' || n == 'U')
        {
            *sum = *sum + 1;
        }
        else if (n == 'D' || n == 'G')
        {
            *sum = *sum + 2;
        }
        else if (n == 'B' || n == 'C' || n == 'M' || n == 'P')
        {
            *sum = *sum + 3;
        }
        else if (n == 'F' || n == 'H' || n == 'V' || n == 'W' || n == 'Y')
        {
            *sum = *sum + 4;
        }
        else if (n == 'K')
        {
            *sum = *sum + 5;
        }
        else if (n == 'J' || n == 'X')
        {
            *sum = *sum + 8;
        }
        else if (n == 'Q' || n == 'Z')
        {
            *sum = *sum + 10;
        }
    }
    return 0;
}
