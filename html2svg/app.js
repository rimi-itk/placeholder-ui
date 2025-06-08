const express = require("express");
const app = express();
const port = 3000;
const puppeteer = require("puppeteer");
const { response } = require("express");

app.get("/", async (req, res) => {
    const url = req.query?.url;
    if (!url) {
        res.status(400).json({ error: "Missing url" });
        return;
    }

    const selector = req.query?.selector;
    if (!selector) {
        res.status(400).json({ error: "Missing selector" });
        return;
    }

    const timeout = Math.min(30000, req.query?.timeout ?? 30000);

    console.log({ url, selector, timeout });

    try {
        const browser = await puppeteer.launch({
            headless: "shell",
            // @FIXME We shouldn't be using `--no-sandbox` (cf. https://pptr.dev/troubleshooting#setting-up-chrome-linux-sandbox)
            args: ["--no-sandbox"],
        });
        const page = await browser.newPage();
        const response = await page.goto(url);

        if (!response.ok()) {
            res.status(response.status()).json({
                message: response.statusText(),
                url,
                selector,
                timeout,
            });
            return;
        }

        console.log(`Waiting for ${selector}`);
        await page.waitForSelector(selector, { timeout });
        console.log(`Found ${selector}`);

        // Note that we cannot use innerHTML here since is will not handle XML
        // properly (self-closing elements will not be closes).
        const content = await page.$eval(selector, (el) =>
            new XMLSerializer().serializeToString(el),
        );
        console.log(content);

        await browser.close();

        res.send({ content });
    } catch (error) {
        console.log({ error });
        res.status(400).json({
            message: error.message,
            url,
            selector,
            timeout,
        });
        return;
    }
});

app.listen(port, () => {
    console.log(`Example app listening on port ${port}`);
});
