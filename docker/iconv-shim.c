/*
 * Macht GNU libiconv über LD_PRELOAD für PHP nutzbar.
 *
 * Die musl-iconv der Alpine-Basis kennt viele PDF-Font-Kodierungen NICHT (z.B.
 * «macintosh»/MacRoman) und gibt für `iconv('macintosh', …)` schlicht `false`
 * zurück. smalot/pdfparser dekodiert MacRoman-Fonts genau darüber
 * (Font.php: `iconv($enc, 'UTF-8//TRANSLIT//IGNORE', …)`); unter musl liefert
 * das leeren Text, und der Budget-Import liest 0 Produktegruppen.
 *
 * GNU libiconv führt seine Funktionen intern als libiconv_* (um nicht mit der
 * libc zu kollidieren). Damit LD_PRELOAD musls iconv im PHP-Prozess ersetzt,
 * braucht es die unpräfixierten Symbole iconv_open/iconv/iconv_close — genau
 * die liefert dieser Shim, indem er an GNU libiconv weiterreicht.
 *
 * Gebaut wird er im Abbild der Anwendung (Dockerfile.php-fpm) und im Abbild
 * des Testlaufs (Dockerfile.php-test): Beide stehen auf musl, und die
 * Budgetbücher werden in beiden gelesen.
 */
#include <stddef.h>

typedef void* iconv_t;

extern iconv_t libiconv_open(const char*, const char*);
extern size_t libiconv(iconv_t, char**, size_t*, char**, size_t*);
extern int libiconv_close(iconv_t);

iconv_t iconv_open(const char* to, const char* from)
{
    return libiconv_open(to, from);
}

size_t iconv(iconv_t cd, char** in, size_t* inlen, char** out, size_t* outlen)
{
    return libiconv(cd, in, inlen, out, outlen);
}

int iconv_close(iconv_t cd)
{
    return libiconv_close(cd);
}
