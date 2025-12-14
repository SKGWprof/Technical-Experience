#include <stdio.h>
#include <cs50.h>
#include <ctype.h>
#include <math.h>


int main(void)
{
    string s = get_string("Text: ");
    int space = 0;
    int stops = 0;
    int chars = 0;
    int other = 0;
    for (int i = 0; s[i] != '\0'; i++)
    {
        if (s[i] == ' ' && s[i + 1] != ' ')
        {
            space = space + 1;
        }
        else if (s[i] == '.' || s[i] == '!' || s[i] == '?')
        {
            stops = stops + 1;
        }
        else if (tolower(s[i]) >= 'a' && tolower(s[i]) <= 'z')
        {
            chars = chars + 1;
        }
        else
        {}
    }
    int words = space + 1;
    float grade = 0.0588 * ((100 * chars) / words) - 0.296 * ((100 * stops) / words) - 15.85;
    int finalgrade = round(grade);
    if (finalgrade < 1)
    {
        printf("Before Grade 1\n");
    }
    else if (grade >= 16)
    {
        printf("Grade 16+\n");
    }
    else
    {
        printf("Grade %i\n", finalgrade);
    }
}
