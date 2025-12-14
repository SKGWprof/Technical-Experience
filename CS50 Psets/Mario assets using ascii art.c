#include <cs50.h>
#include <stdio.h>

int main(void)
{
    int height;
    do
    {
        height = get_int("what height do you want? ");
    }
    while (height < 1 || height > 8);
    for (int i = 0; i < height; i++)
    {
        for (int x = 0; x < height - i - 1; x++)
        {
            printf(" ");
        }
        for (int n = 0; n < i + 1; n++)
        {
            printf("#");
        }
        printf("  ");
        for (int y = 0; y < i + 1; y++)
        {
            printf("#");
        }
        printf("\n");
    }
}
