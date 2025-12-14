#include "helpers.h"
#include <math.h>

// Convert image to grayscale
void grayscale(int height, int width, RGBTRIPLE image[height][width])
{
    for (int i = 0; i < height; i++)
    {
        for (int j = 0; j < width; j++)
        {
            BYTE n =
                round((image[i][j].rgbtBlue + image[i][j].rgbtGreen + image[i][j].rgbtRed) / 3.0);
            image[i][j].rgbtBlue = n;
            image[i][j].rgbtGreen = n;
            image[i][j].rgbtRed = n;
        }
    }
    return;
}

// Reflect image horizontally
void reflect(int height, int width, RGBTRIPLE image[height][width])
{
    for (int i = 0; i < height; i++)
    {
        int n = (width / 2);
        for (int j = 0; j < n; j++)
        {
            BYTE tmpone = image[i][j].rgbtBlue;
            BYTE tmptwo = image[i][j].rgbtGreen;
            BYTE tmpthree = image[i][j].rgbtRed;
            image[i][j].rgbtBlue = image[i][width - 1 - j].rgbtBlue;
            image[i][j].rgbtGreen = image[i][width - 1 - j].rgbtGreen;
            image[i][j].rgbtRed = image[i][width - 1 - j].rgbtRed;
            image[i][width - 1 - j].rgbtBlue = tmpone;
            image[i][width - 1 - j].rgbtGreen = tmptwo;
            image[i][width - 1 - j].rgbtRed = tmpthree;
        }
    }
    return;
}

// Blur image
void blur(int height, int width, RGBTRIPLE image[height][width])
{
    RGBTRIPLE imagetmp[height][width];
    for (int i = 0; i < height; i++)
    {
        for (int j = 0; j < width; j++)
        {
            long blue = 0;
            long green = 0;
            long red = 0;
            float n = 0.0;
            for (int t = i - 1; t <= i + 1; t++)
            {
                if (t > height - 1)
                {
                    break;
                }
                else if (t >= 0)
                {
                    for (int c = j - 1; c <= j + 1; c++)
                    {
                        if (c > width - 1)
                        {
                            break;
                        }
                        else if (c >= 0)
                        {
                            blue = blue + image[t][c].rgbtBlue;
                            green = green + image[t][c].rgbtGreen;
                            red = red + image[t][c].rgbtRed;
                            n = n + 1;
                        }
                        else if (c < 0)
                        {
                        }
                    }
                }
                else if (t < 0)
                {
                }
            }
            imagetmp[i][j].rgbtBlue = round(blue / n);
            imagetmp[i][j].rgbtGreen = round(green / n);
            imagetmp[i][j].rgbtRed = round(red / n);
        }
    }
    for (int i = 0; i < height; i++)
    {
        for (int j = 0; j < width; j++)
        {
            image[i][j].rgbtBlue = imagetmp[i][j].rgbtBlue;
            image[i][j].rgbtGreen = imagetmp[i][j].rgbtGreen;
            image[i][j].rgbtRed = imagetmp[i][j].rgbtRed;
        }
    }
    return;
}

// Detect edges
void edges(int height, int width, RGBTRIPLE image[height][width])
{
    typedef struct
{
    long  rgbtBlue;
    long  rgbtGreen;
    long  rgbtRed;
}
RGBTRIPLEE;
    RGBTRIPLEE imagetmp[height][width];
    for (int i = 0; i < height; i++)
    {
        for (int j = 0; j < width; j++)
        {
            long blue = 0;
            long green = 0;
            long red = 0;
            for (int t = i - 1; t <= i + 1; t++)
            {
                if (t == i - 1 || t == i + 1)
                {
                    int n = -1;
                    if (t > height - 1)
                    {
                        break;
                    }
                    else if (t >= 0)
                    {
                        for (int c = j - 1; c <= j + 1; c++)
                        {
                            if (c > width - 1)
                            {
                                break;
                            }
                            else if (c >= 0)
                            {
                                blue = blue + image[t][c].rgbtBlue * n;
                                green = green + image[t][c].rgbtGreen * n;
                                red = red + image[t][c].rgbtRed * n;
                                n = n + 1;
                            }
                            else if (c < 0)
                            {
                                n = n + 1;
                            }
                        }
                    }
                    else if (t < 0)
                    {
                    }
                }
                if (t == i)
                {
                    int n = -2;
                    if (t >= 0)
                    {
                        for (int c = j - 1; c <= j + 1; c++)
                        {
                            if (c > width - 1)
                            {
                                break;
                            }
                            else if (c >= 0)
                            {
                                blue = blue + image[t][c].rgbtBlue * n;
                                green = green + image[t][c].rgbtGreen * n;
                                red = red + image[t][c].rgbtRed * n;
                                n = n + 2;
                            }
                            else if (c < 0)
                            {
                                n = n + 2;
                            }
                        }
                    }
                    else if (t < 0)
                    {
                    }
                    else if (t > height - 1)
                    {
                        break;
                    }
                }
            }

            long bluey = 0;
            long greeny = 0;
            long redy = 0;
            for (int t = j - 1; t <= j + 1; t++)
            {
                if (t == j - 1 || t == j + 1)
                {
                    int n = -1;
                    if (t > width - 1)
                    {
                        break;
                    }
                    else if (t >= 0)
                    {
                        for (int c = i - 1; c <= i + 1; c++)
                        {
                            if (c > height - 1)
                            {
                                break;
                            }
                            else if (c >= 0)
                            {
                                bluey = bluey + image[c][t].rgbtBlue * n;
                                greeny = greeny + image[c][t].rgbtGreen * n;
                                redy = redy + image[c][t].rgbtRed * n;
                                n = n + 1;
                            }
                            else if (c < 0)
                            {
                                n = n + 1;
                            }
                        }
                    }
                    else if (t < 0)
                    {
                    }
                }
                if (t == j)
                {
                    int n = -2;
                    if (t > width - 1)
                    {
                        break;
                    }
                    else if (t >= 0)
                    {
                        for (int c = i - 1; c <= i + 1; c++)
                        {
                            if (c > height - 1)
                            {
                                break;
                            }
                            else if (c >= 0)
                            {
                                bluey = bluey + image[c][t].rgbtBlue * n;
                                greeny = greeny + image[c][t].rgbtGreen * n;
                                redy = redy + image[c][t].rgbtRed * n;
                                n = n + 2;
                            }
                            else if (c < 0)
                            {
                                n = n + 2;
                            }
                        }
                    }
                    else if (t < 0)
                    {
                    }
                }
            }
            imagetmp[i][j].rgbtRed = round(sqrt((pow(red, 2) + pow(redy, 2))));
            imagetmp[i][j].rgbtGreen = round(sqrt((pow(green, 2) + pow(greeny, 2))));
            imagetmp[i][j].rgbtBlue = round(sqrt((pow(blue, 2) + pow(bluey, 2))));
        }
    }
    for (int i = 0; i < height; i++)
    {
        for (int j = 0; j < width; j++)
        {
            if (imagetmp[i][j].rgbtRed > 255)
            {
                image[i][j].rgbtRed = 255;
            }
            else
            {
                image[i][j].rgbtRed = imagetmp[i][j].rgbtRed;
            }
        }
    }
    for (int i = 0; i < height; i++)
    {
        for (int j = 0; j < width; j++)
        {
            if (imagetmp[i][j].rgbtBlue > 255)
            {
                image[i][j].rgbtBlue = 255;
            }
            else
            {
                image[i][j].rgbtBlue = imagetmp[i][j].rgbtBlue;
            }
        }
    }
    for (int i = 0; i < height; i++)
    {
        for (int j = 0; j < width; j++)
        {
            if (imagetmp[i][j].rgbtGreen > 255)
            {
                image[i][j].rgbtGreen = 255;
            }
            else
            {
                image[i][j].rgbtGreen = imagetmp[i][j].rgbtGreen;
            }
        }
    }
    return;
}
