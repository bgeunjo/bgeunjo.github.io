---
layout: post
title:  "Matrix Factorization with PyTorch using movielens dataset"
date:   2021-02-01
categories: ["2021","ai"]
update:   2021-02-01
comment: true
tags: [ai,recommend]
---



오랜만에 글입니다. 최근에 너무 나태하게 산 것 같아 현타가 오네요.. 해킹공부와 추천 시스템 공부를 이번 방학에는 주로 해왔는데, 추천시스템의  협업 필터링 방식 중 Model-based의 대표적인 기술 **Matrix Factorization(이하 MF)**을 pytorch로 구현한 것에 대해 이야기해보려고 합니다.

협업 필터링과 MF에 대해 정리한 글이 많은데, 궁금하신 분들은 각각 [여기](https://scvgoe.github.io/2017-02-01-%ED%98%91%EC%97%85-%ED%95%84%ED%84%B0%EB%A7%81-%EC%B6%94%EC%B2%9C-%EC%8B%9C%EC%8A%A4%ED%85%9C-(Collaborative-Filtering-Recommendation-System)/)와 [여기](https://jeongchul.tistory.com/553)를 참고하시면 좋을 것 같습니다.

제가 구현 실력이 뛰어난 것은 아니기 때문에 참고만 한다는 느낌으로 봐주십쇼🤣

## 📄 프로토타입 

저는 PyTorch로 개발할 때 코드를 총 6부분으로 구분합니다.

- **Setting**
- **Dataset & Dataloader**
- **Model**
- **Criterion(loss function) & Optimizer**
- **train & test**
- **run**

처음 코드가 시작할 때는 다음과 같은 형태를 가지겠네요.

**MF.py** :

``` python
import torch
from torch import nn
...

# Setting 
def check_positive(val):
    val = int(val)
    if val <=0:
        raise argparse.ArgumentError(f'{val} is invalid value. epochs should be positive integer')
    return val

parser = argparse.ArgumentParser(description='matrix factorization with pytorch')
parser.add_argument('--epochs', '-e', type=check_positive, default=30)
parser.add_argument('--batch', '-b', type=check_positive, default=32)
parser.add_argument('--lr', '-l', type=float, help='learning rate', default=1e-3)


# Dataset & Dataloader

# Model

# Criterion & Optimizer

# train & test

# run
```

### Dataset / Dataloader 

PyTorch에서는 MNIST 같이 유명한 데이터에 대해서는 기본적으로 내장된 데이터셋을 제공합니다. 그런데 movielens는 없더라구요 ㅠㅠ 그래서 한번 만들어봤습니다. 이 과정이 필수는 아니지만 PyTorch에서 제공하는 **Dataloader**는 전체 데이터셋을 여러 batch들로 나누고, 섞고, 반복적으로 접근할 수 있게 해줍니다. 그것도 아주 쉬운 방법으로요! 👏 그래서 데이터를 처리하는 과정을 단순화할 수 있고, 코드의 가독성도 높여줍니다.

저는 movielens dataset 을 사용했는데, [여기](https://grouplens.org/datasets/movielens/)에 가시면 여러 종류의 데이터셋이 있습니다. 저는 이 중 **MovieLens Latest Datasets**을 사용했습니다.

이 파트에서 저희가 해야할 일은 커스텀 데이터셋을  `torch.utils.data.Dataset`에 상속하고, 아래와 같은 메소드를 오버라이드하는 것입니다. 

- `len(dataset)` 에서 호출되는 `__len__` 은 데이터셋의 크기를 리턴해야합니다.
- `dataset[i]` 에서 호출되는 `__getitem__` 은 i번째 샘플을 찾는데 사용됩니다.

[여기](https://tutorials.pytorch.kr/beginner/data_loading_tutorial.html)에 자세한 설명이 나와있으니 보면서 따라하시면 쉽게 하실 수 있을 겁니다.

movielens dataset을 다운로드 받으면 여러 csv 파일이 있는데 그 중 저희가 사용할 데이터셋은 `ratings.csv` 입니다. 왜냐하면 **협업 필터링**의 Model based 방식인 MF를 구현 중 이니까요!😁 "`User u`가 `Item i`에 대해 `Rating r`을 남겼다" 라는 사실을 알고 싶은 겁니다.

![image](https://user-images.githubusercontent.com/51329156/106460584-e9030400-64d6-11eb-91b9-d229df2292b9.png)

이 중 저희가 현재로써는 필요없는 `timestamp` 컬럼을 잘라내고 가져오겠습니다. csv파일을 dataframe으로 가져오기 위해 **pandas** 모듈을 사용했습니다.

**MF.py** :

``` python
import torch
from torch import nn
import pandas as pd
...
# Setting
# Dataset & Dataloader
ratings_df = pd.read_csv('data/movielens/ratings.csv').drop(columns=['timestamp'])

# Model

# Criterion & Optimizer

# train & test

# run
```

그리고 위에서 설명한대로 `torch.utils.data.Dataset`에 상속한 커스텀 데이터셋을 만들고 두 메소드를 오버라이드하면 됩니다. 저는 데이터를 학습용, 평가용으로 나누기 위해 **sklearn**에서 제공하는 `train_test_split` 함수를 사용했습니다. `__getitem__`에서는 index를 받아 index에 해당하는 데이터를 뽑고, `__len__`에서는 데이터셋의 사이즈를 반환합니다.

그리고 만든 데이터셋을 이용해서 데이터로더까지 만들어보겠습니다.

**MF.py** 

```python
import torch
from torch import nn
import pandas as pd
from torch.utils.data import Dataset
from sklearn.model_selection import train_test_split
...

# Dataset & Dataloader
ratings_df = pd.read_csv('data/movielens/ratings.csv').drop(columns=['timestamp'])

class MovieLensDataset(Dataset):
    """
    :param df: rating dataframe, columns: ['userId', 'movieId', 'rating', ...]
    :param train: Using train_test_split from sklearn, if True, train_df is used else test_df.
    """
    def __init__(self, df, transform=None, train_size=0.8, test_size=0.2, train=False):
        self.df = df
        self.train = train
        self.train_size = train_size
        self.test_size = test_size
        self.transform = transform
        self.train_df, self.test_df = train_test_split(self.df, test_size=self.test_size, train_size=self.train_size, random_state=1234)
        if self.train == True:
            self.df = self.train_df
        else:
            self.df = self.test_df
    def __len__(self):
        return len(self.df)
    def __getitem__(self, index):
        user = torch.LongTensor([self.df.userId.values[index]])
        item = torch.LongTensor([self.df.movieId.values[index]])
        target = torch.FloatTensor([self.df.rating.values[index]])
        return (user, item, target)
    
train_dataset = MovieLensDataset(df=ratings_df, train=True)
test_dataset = MovieLensDataset(df=ratings_df, train=False)

train_loader = DataLoader(dataset=train_dataset, batch_size=args.batch, shuffle=True) 
test_loader = DataLoader(dataset=test_dataset, batch_size=args.batch, shuffle=True) 
# Model

# Criterion & Optimizer

# train & test

# run
```

이제 저희가 사용할 데이터를 가져오고 사용할 준비를 마쳤습니다! 🎉

### Model

이제 Matrix Factorization을 수행할 모델을 구현해야 합니다. Matrix Factoriztion에 대해 간단히 설명드리면, "User와 Item을 각각 latent vector로 표현한 후, 이 둘을 행렬곱"해서 "원래의 Interaction Matrix를 예측 / 완성"하는 것입니다. latent vector는 각각의 요소가 가지고 있는 잠재적인 특성을 컴퓨터가 이해할 수 있는 '값'들로 나타낸 것이라 생각하시면 됩니다.

**예측**은 원래의 값과 모델이 만들어 낸 값과의 차이를 줄이는 과정입니다. 때문에 이 과정은 **Criterion**에서 처리합니다. 그럼 이 파트에서 해야하는 일은 "User와 Item을 각각 latent vector로 표현한 후, 이 둘을 행렬곱"하는 부분이겠네요.

latent vector로 표현하는 것은 임베딩을 사용해도 되고, 랜덤값을 사용해도 됩니다. 저는 임베딩을 사용했습니다.

**MF: py** :

```python
import torch
from torch import nn
import pandas as pd
from torch.utils.data import Dataset
from sklearn.model_selection import train_test_split
...

# Dataset & Dataloader
ratings_df = pd.read_csv('data/movielens/ratings.csv').drop(columns=['timestamp'])
n_users, n_items = args.batch * ratings_df.shape[0], args.batch * ratings_df.shape[0]
... 
train_dataset = MovieLensDataset(df=ratings_df, train=True)
test_dataset = MovieLensDataset(df=ratings_df, train=False)

train_loader = DataLoader(dataset=train_dataset, batch_size=args.batch, shuffle=True) 
test_loader = DataLoader(dataset=test_dataset, batch_size=args.batch, shuffle=True) 
# Model
class MatrixFacotirzation(nn.Module):
    def __init__(self, n_users, n_items, n_factor = 20):
        """
        :param n_users: number of users
        :param n_items: number of items
        :param n_factor: size of latent vector 

        """
        super().__init__()
        self.user_embedding = nn.Embedding(num_embeddings= n_users, embedding_dim=n_factor)
        self.item_embedding = nn.Embedding(num_embeddings=n_items, embedding_dim=n_factor)
    def forward(self, user, item):
        return torch.bmm(self.user_embedding(user), torch.transpose(self.item_embedding(item),1,2)) # (32,1,100) x (32,100,1)
model = MatrixFacotirzation(n_users, n_items, n_factor=100).to(device)
# Criterion & Optimizer

# train & test

# run
```

각 user와 item을 100차원의 latent vector로 나타낸 후 `torch.bmm`을 이용해 **batch matmul**을 해주고 있습니다. `bmm()`을 사용한 이유는 dataloader가 dataset의 `__getitem__()`을 이용해 데이터를 가져올 때 지정해준 batch_size 만큼 가져오기 때문입니다. 그래서 텐서의 형태가 원래는 `(1,)`라면, batch_size가 32일 때 `(32,1)`이 되는거죠. 이럴 때 batch_size를 신경안쓰고 가져온 행렬끼리의 곱만 하고 싶을 때 사용하는 것이 `bmm()`입니다. 행렬곱을 위해 Item은 transpose해주었습니다.

### Criterion & Optimizer

이제 모델도 만들었으니 loss function과 optimizer를 지정해야 합니다. 이 부분은 PyTorch에 내장된 것을 쓰면 되기 때문에 코드 몇 줄로 끝내면 됩니다. 다만 저희가 사용할 **RMSELoss**는 PyTorch에서 제공해주지 않아서, `MSELoss()`의 결과값에 square root를 취했습니다.

**MF.py** :

``` python
import torch
from torch import nn
import pandas as pd
from torch.utils.data import Dataset
from sklearn.model_selection import train_test_split
...

# Dataset & Dataloader
ratings_df = pd.read_csv('data/movielens/ratings.csv').drop(columns=['timestamp'])
n_users, n_items = args.batch * ratings_df.shape[0], args.batch * ratings_df.shape[0]
... 
train_dataset = MovieLensDataset(df=ratings_df, train=True)
test_dataset = MovieLensDataset(df=ratings_df, train=False)

train_loader = DataLoader(dataset=train_dataset, batch_size=args.batch, shuffle=True) 
test_loader = DataLoader(dataset=test_dataset, batch_size=args.batch, shuffle=True) 
# Model
...
model = MatrixFacotirzation(n_users, n_items, n_factor=100).to(device)
# Criterion & Optimizer
class RMSEloss(nn.Module):
    """
    square root of MSELoss()
    According to docs(https://pytorch.org/docs/stable/generated/torch.nn.MSELoss.html), 
    'mean' is set by default for 'reduction' and can be avoided by 'reduction="sum"'
    """
    def __init__(self, eps=1e-6):
        super().__init__()
        self.mse = nn.MSELoss().to(device)
        self.eps = torch.FloatTensor([eps]).to(device)
    def forward(self, pred, rating):
        loss = torch.sqrt(self.mse(pred, rating).to(device) + self.eps)
        return loss

criterion = RMSEloss().to(device)
optimizer = optim.Adam(model.parameters(), lr=args.lr)
# train & test

# run
```

### **train & test**

거의 다 왔네요! 👍 이제 학습, 평가를 수행할 각각의 함수를 만들어 주면 끝입니다. 전체적인 흐름은 다음과 같습니다.

- **dataloader**를 이용해 전체 데이터를 쪼갠 batch에 접근
  - **model**을 이용해 예측
  - 실제 값과 비교, loss값 계산
  - `backward()` 수행
  - 파라미터 수정

각각의 과정이 이미 코드로 짜여져 있거나, PyTorch 내부에서 제공하는 기능이기 때문에 이 부분도 코드 몇 줄로 끝낼 수 있습니다. 결과를 저장하고, 출력하는 코드만 조금 곁들이면 되겠네요. train 과 test는 각각 train_dataset, test_dataset을 사용하는 동일한 과정입니다. 

**MF.py** :

``` python
import torch
from torch import nn
import pandas as pd
from torch.utils.data import Dataset
from sklearn.model_selection import train_test_split
...

# Dataset & Dataloader
ratings_df = pd.read_csv('data/movielens/ratings.csv').drop(columns=['timestamp'])
n_users, n_items = args.batch * ratings_df.shape[0], args.batch * ratings_df.shape[0]
... 
train_dataset = MovieLensDataset(df=ratings_df, train=True)
test_dataset = MovieLensDataset(df=ratings_df, train=False)

train_loader = DataLoader(dataset=train_dataset, batch_size=args.batch, shuffle=True) 
test_loader = DataLoader(dataset=test_dataset, batch_size=args.batch, shuffle=True) 
# Model
...
model = MatrixFacotirzation(n_users, n_items, n_factor=100).to(device)
# Criterion & Optimizer
...
criterion = RMSEloss().to(device)
optimizer = optim.Adam(model.parameters(), lr=args.lr)
# train & test
def train(epoch):
    process = []
    model.train()
    for idx, (user, item, target) in enumerate(train_loader):
        user, item, target = user.to(device), item.to(device), target.to(device)
        optimizer.zero_grad()
        pred = torch.flatten(model(user, item), start_dim = 1).to(device)
        loss = criterion(pred, target)
        loss.backward()
        optimizer.step()

        process.append(loss.item())
        if idx % 10 == 0:
            print (f'[*] Epoch: {epoch} [{idx * args.batch} / {len(train_loader)}] RMSE: {sum(process) / len(process)}')
    return sum(process) / len(process)

def test():
    process = []
    model.train()
    for idx, (user, item, target) in enumerate(test_loader):
        user, item, target = user.to(device), item.to(device), target.to(device)
        optimizer.zero_grad()
        pred = torch.flatten(model(user, item), start_dim = 1).to(device)
        loss = criterion(pred, target)
        loss.backward()
        optimizer.step()
        process.append(loss.item())
    print (f'[*] Test RMSE: {sum(process) / len(process)}')
    return sum(process) / len(process)

# run
```

### **run**

이제 다 끝났습니다. 실행만 빼고요!😊 epoch만큼 반복시키고, 각각의 결과를 저장한 후 그래프로 나타내면 모든 코드가 완성됩니다.

``` python
...

if __name__=="__main__":
    train_rmse = torch.Tensor([]).to(device)
    test_rmse = torch.Tensor([]).to(device)
    for epoch in range(args.epochs):
        train_rmse = torch.cat((train_rmse, train(epoch)),0)
        test_rmse = torch.cat((train_rmse, test()),0)
    plt.plot(range(args.epochs),train_rmse)
    plt.plot(range(args.epochs),test_rmse)
    plt.xlabel('epoch')
    plt.ylabel('RMSE')
    plt.show()
```

## ❗ Conclusion

![Figure_2](https://user-images.githubusercontent.com/51329156/106496851-b3731080-6500-11eb-9bc1-5937d6605189.png)

주황색 선은 test 결과 이고, 파란색 선은 train 결과입니다. 거의 비슷하네요. 저는 epoch을 30으로 했는데 결과를 보니 20으로 했을 때와 별 차이가 없습니다.

## 🔎 NG

이 코드를 작성하면서 몇가지 문제들을 마주쳤습니다... 그 부분에 대해 제가 생각하는 원인과 해결방법을 말씀 드리고 글을 마무리하려고 합니다. 정확하진 않으니 참고만 해주세요!

### 1. Loss가 줄어들지 않음

처음에 코드를 짤 때는 ratings.csv를 그대로 사용하지 않고, **pandas**의 `pivot()`을 사용해 user와 item의 interaction matrix로 만들어 사용했습니다. 그렇게 되면 중간에 빈 값이 생기게 되는데, **numpy**의 `nonzero()`를 사용해  채워진 값만 가져오는 식으로 했습니다. 근데 이 부분에서 어떤 문제가 있었는지... loss가 15~18에서 왔다갔다 하면서 도저히 감소를 하지 않았습니다.. 그래서 그냥 원래 데이터를 그대로 사용하는 방식으로 돌아왔더니 정상화되었습니다. 그 와중에 바꾼 게 또 있었기 때문에 확신은 못하겠지만, 제가 생각하기에는 그렇습니다!

### 2.  (user * item).sum(1) ??

이 부분은 문제라기 보단 의문이 들어서 넣어봤습니다. 

위의 연산은 user와 item 행렬을 elemet wise 하게 연산한 후, 같은 row에 있는 값들을 다 더하겠다는 의미입니다.  구글링을 해보면 많은 사람들이 이렇게 MF를 구현했는데... 저는 이해가 되지 않았습니다. 저는 `matmul(user, item.T)`를 해야 의미상 맞지 않나라고 생각했거든요. 여기에 대해서는 더 알아보는 중입니다!



이렇게 MF를 PyTorch로 구현하는 데에 성공했네요. 다음에는 아래의 순서대로 논문을 읽고, 구현해보려고 합니다. 주변에 추천시스템을 공부하는 친구가 없어 외로웠는데 갓갓 선배님 *adldotori* 께서 이걸 주셨습니다. 더 열심히 해야겠네요.. 글 읽고 모르는 부분은 제 프로필에 있는 연락처로 연락 주시면 답변드리겠습니다. 감사합니다! 😆

![KakaoTalk_20210201_224448751](https://user-images.githubusercontent.com/51329156/106468911-e0fc9180-64e1-11eb-8be7-cc61c51c3d8b.jpg)