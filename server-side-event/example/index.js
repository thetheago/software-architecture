const express = require("express")
const port = 8080;
const cors = require("cors")
// const {createClient} = require("redis")


const app = express()
app.use(cors());

app.get('/stream', async (req, res) => {
    res.header("content-type", "text/event-stream"); // header obrigatorio para SSE
    res.header("connection", "keep-alive"); // somente para HTTP antigo como 1.0, para falar para manter a con alive

      setInterval(() => {
      res.write(
          "event: evento" + "\n" +    // event nao e obrigatorio mas vc pode passar para a aplicacao poder trabalhar
          "data: " + Math.random() + "\n\n" // "data: " e "\n\n" sao obrigatorios para cada msg
      );
    }, 3000);

    req.on("close", () => {
      res.end();
    });

  // const redisClient = createClient({"url": "redis://redis:6379"});
  // redisClient.on('error', err => console.log('Redis Client Error', err));

  // await redisClient.connect();

  // await redisClient.subscribe("notifications", (message) => {
  //   res.write("data: " + message + "\n\n");
  // })

  // No redis-cli : PUBLISH notifications "Minha primeira mensagem!"
});

app.listen(port, () => {});
