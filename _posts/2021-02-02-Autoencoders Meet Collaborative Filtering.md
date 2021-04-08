---
layout: post
title:  "AutoRec 논문 리뷰, PyTorch로 구현하기"
date:   2021-02-03
categories: ["2021","ai"]
update:   2021-02-03
use_math: true
comment: true
tags: [ai,recommend]
---



내용이 워낙 간단하고 글도 짧아서 리뷰는 모델까지만 간단히 하고 바로 구현으로 넘어가겠습니다.



## AutoRec: Autoencoders Meet Collaborative Filtering

### ABSTRACT

이 논문에서는 협업필터링(이하 CF)를 위한 autoencoder 프레임워크를 제시합니다. 저자의 경험에 따르면 **AutoRec**의 모델은 작고 효율적으로 학습이 가능하고 Movielens 데이터셋과 Netflix 데이터셋에 대해 당시 state-of-the-art CF 기술이었던 biased MF, RBM-CF, LLORMA보다 월등한 성능을 냈다고 하네요.

### 👀 What is Autoencoder ?

인트로에서 설명했듯이 이 모델에서는 autoencoder를 사용합니다. 그렇다면 autoencoder가 대체 뭘까요?

>오토 인코더는 감독되지 않은 방식으로 효율적인 데이터 코딩을 학습하는 데 사용되는 인공 신경망의 한 유형입니다. 오토 인코더의 목적은 네트워크가 신호 "노이즈"를 무시하도록 훈련함으로써 일반적으로 차원 감소를 위한 데이터 셋에 대한 표현을 학습하는 것입니다.

*출처 : [위키피디아](https://en.wikipedia.org/wiki/Autoencoder)*

입력에서 출력으로 복사하는 신경망인데, 중간에 노이즈를 추가하거나 해서 단순 복사하지 못하도록 막고 이런 과정들을 통해서 데이터셋을 효율적으로 표현하는 방법을 학습합니다. 

![image](https://user-images.githubusercontent.com/51329156/106574235-00e19300-657e-11eb-8a99-c5192cd8cabd.png)

그림에서도 알 수 있듯이 오토인코더는 항상 인코더와 디코더, 두 부분으로 구성되어 있습니다.

- **인코더(encoder)** : 인지 네트워크(recognition network)라고도 하며, 입력을 내부 표현으로 변환합니다.

- **디코더(decoder)** : 생성 네트워크(generative nework)라고도 하며, 내부 표현을 출력으로 변환합니다.

입력과 출력층의 뉴런 수가 동일하다는 것만 제외하면 일반적인 MLP와 동일한 구조인 것 또한 알 수 있습니다. **Loss function**은 입력과 재구성된 입력값, 즉 출력의 차이를 가지고 계산합니다. 

정리해보면 오토인코더를 통해 입력값을 **재구성(reconstruction)**하고 입력 데이터에서 중요한 특성들을 학습하는 친구입니다. 오토인코더도 종류가 많은데, 후에 필요할 때 추가적으로 다루겠습니다.

**참고**

> 🚀 [오토인코더 (AutoEncoder)](https://excelsior-cjh.tistory.com/187)

### THE AUTOREC MODEL

CF에서 m명의 사용자와, n개의 아이템에 대해 rating matrix $R \in \mathbb{R}^{m \times n}$를 가집니다. 각 User u는 item에 대한 평가 벡터 $r^{(u)} = (R_{u1},R_{u2},...,R_{un})$를 가지고 Item i는 마찬가지로 $r^{(i)} = (R_{1i},R_{2i},...,R_{mi})$로 표현될 수 있습니다.  앞으로의 설명은 Item-based를 기반으로 하겠습니다.  이 모델에서 하려는 일은 원래 입력값 $r^{(i)}$와 오토인코더를 통해 재구성한 입력값 간의 오차를 구해 그 오차를 줄여나가는 것입니다. 입력을 재구성한 값, 즉 오토인코더를 거친 output은 수식으로 다음과 같이 나타냅니다.

$$h(r;\theta) = f(W \\cdot g(Vr + \mu) + b)$$

$ f,g $는 각각 활성화 함수이고, 파라미터 각각의 사이즈는 다음과 같습니다.

$W \\in R^{d \times k} ,V \in R^{k \times d}, \mu \in R^{k},b \in R^{d}$

여기서 $d$는 m 혹은 n이고 $k$는 hidden_layer의 feature 수 입니다. 

Objective funtion은 L2 loss를 손실함수로 사용하는 L2 Regularization을 사용합니다.

![image](https://user-images.githubusercontent.com/51329156/106887861-1d6dfe80-6729-11eb-9037-79926af7e822.png)


$\left \|\cdot\right \|_{O}^{2}$의 의미는 관측된 rating, 즉 interaction matrix에서 비어있지 않은 요소에 대해서만 고려하겠다는 의미입니다. MF와는 달리 $g(\cdot)$에서 non-linear 한 활성화 함수를 사용함으로써 latent vector를 더 잘 표현할 수 있다고 합니다.

## PyTorch로 구현

### 📖 Dataset

전체 데이터셋을 train, test 셋으로 나누고 `__getitem__()`에서는 각 item에 대한 평가 벡터를 가져옵니다.

``` python
device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

class MovielensDataset(Dataset):
    def __init__(self, path, index, columns, values, train_ratio=.9, test_ratio=.1, train = None):
        super().__init__()
        # self.index = index
        # self.columns = columns
        # self.values = values
        self.rating_df = pd.read_csv(path)
        self.inter_matrix = self.rating_df.pivot(index=index, columns=columns, values=values).fillna(0).to_numpy()

        self.train_ratio = train_ratio
        self.test_ratio = test_ratio

        self.total_indices = np.arange(len(self.inter_matrix))
        self.test_indices = np.random.choice(self.total_indices, size=(int(len(self.inter_matrix) * self.test_ratio),), replace=False)
        self.train_indices = np.array(list(set(self.total_indices)-set(self.test_indices)))

        if train != None:
            if train == True:
                self.inter_matrix = self.inter_matrix[self.train_indices]
            elif train == False:
                self.inter_matrix = self.inter_matrix[self.test_indices] 
    def __len__(self):
        return len(self.inter_matrix)
    def __getitem__(self, index: int):
        """
        get rating vector of one item(or user) : [0., 0., 4., 3., ..., 0., 0.]
        """
        return torch.Tensor(self.inter_matrix[index]).to(device)
```

### 📖 Model

오토인코더는 **encoder**와 **decoder**로 이루어져있다고 했으니 모델에서도 똑같이 구현해줍니다. 위의 수식에서는 $h(\cdot)$에 해당하는 코드입니다.

``` python
class AutoRec(nn.Module):
    """
    input -> hidden -> output(output.shape == input.shape)
    encoder: input -> hidden
    decoder: hidden -> output
    """
    def __init__(self, n_users, n_items, n_factors=200):
        super(AutoRec, self).__init__()
        self.n_factors = n_factors
        self.n_users = n_users
        self.n_items = n_items

        self.encoder = nn.Sequential(
            nn.Linear(self.n_users,self.n_factors),
            nn.Sigmoid(),
        )
        self.decoder = nn.Sequential(
            nn.Linear(self.n_factors, self.n_users),
            nn.Identity(),
        )

    def forward(self, x):
        return self.decoder(self.encoder(x)).to(device)
```

`Sigmoid()`와 `Identity()`는 논문에서 성능이 가장 좋은 조합이었습니다.

### 📖 Criterion

논문에서 관측된 rating에 대해서만 오차를 검사한다고 했으니 비어있는 값은 빼고 계산하기 위해 기존에 사용하던 RMSE 클래스에 약간 수정을 했습니다. 제가 한 방법 말고도 numpy의 `nonzero()`를 사용해도 좋을 것 같습니다.

``` python
class MRMSELoss(nn.Module):
    """
    MaskedRootMSELoss() uses only observed ratings.
    According to docs(https://pytorch.org/docs/stable/generated/torch.nn.MSELoss.html), 
    'mean' is set by default for 'reduction' and can be avoided by 'reduction="sum"'
    """
    def __init__(self, reduction='sum'):
        super().__init__()
        self.reduction = reduction
        self.mse = nn.MSELoss(reduction=self.reduction).to(device)
    def forward(self, pred, rating):
        mask = rating != 0
        masked_pred = pred * mask.float()
        num_observed = torch.sum(mask).to(device) if self.reduction == 'mean' else torch.Tensor([1.]).to(device)
        loss = torch.sqrt(self.mse(masked_pred, rating) / num_observed)
        return loss
```

Objective function에서 $\lambda$ 는 Optimizer에 `weight_decay`값으로 주면 됩니다.

실행코드는 아래와 같습니다.

``` python
import torch
from torch import nn, optim,cuda
from models import AutoRec, MRMSELoss
from data import MovielensDataset
from torch.utils.data import DataLoader, Dataset
import argparse
import os
import pandas as pd
import numpy as np
import matplotlib.pyplot as plt

"""
AutoRec: Autoencoders Meet Collaborative Filtering implementation with PyTorch
"""
# Setting
def check_positive(val):
    val = int(val)
    if val <=0:
        raise argparse.ArgumentError(f'{val} is invalid value. epochs should be positive integer')
    return val

parser = argparse.ArgumentParser(description='AutoRec with PyTorch')
parser.add_argument('--epochs', '-e', type=check_positive, default=30)
parser.add_argument('--batch', '-b', type=check_positive, default=32)
parser.add_argument('--lr', '-l', type=float, help='learning rate', default=1e-3)
parser.add_argument('--wd', '-w', type=float, help='weight decay(lambda)', default=1e-2)
parser.add_argument('--ksize', '-k', type=check_positive, help='hidden layer feature_size', default=200)

args = parser.parse_args()

path = 'data/movielens/'
device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
n_users, n_items = (9724, 610)

# Dataset & Dataloader

train_dataset = MovielensDataset(path=os.path.join(path,'ratings.csv'), index='movieId', columns='userId', train=True)
test_dataset = MovielensDataset(path=os.path.join(path,'ratings.csv'), index='movieId', columns='userId', train=False)

train_loader = DataLoader(dataset=train_dataset, batch_size=args.batch,shuffle=True)
test_loader = DataLoader(dataset=test_dataset, batch_size=args.batch,shuffle=True)

# Model & Criterion
model = AutoRec(n_users=n_users, n_items=n_items, n_factors=200).to(device)

# Criterion & Optimizer
criterion = MRMSELoss().to(device)
optimizer = optim.Adam(model.parameters(), weight_decay= args.wd, lr=args.lr)

# Train & Test
def train(epoch):
    process = []
    for idx, (data,) in enumerate(train_loader):
        optimizer.zero_grad()
        pred = model(data)
        loss = criterion(pred,data)
        loss.backward()
        optim.step()
        process.append(loss.item())
        if idx % 100 == 0:
            print (f"[+] Epoch {epoch} [{idx * args.batch} / {len(train_loader.dataset)}] - RMSE {sum(process) / len(process)}")
    return torch.Tensor(sum(process) / len(process)).to(device)
def test():
    process = []
    for idx, (data,) in enumerate(test_loader):
        optimizer.zero_grad()
        pred = model(data)
        loss = criterion(pred,data)
        loss.backward()
        optim.step()
        process.append(loss.item())
    print (f"[*] Test RMSE {sum(process) / len(process)} ")
    return torch.Tensor(sum(process) / len(process)).to(device)
    
# Run
if __name__=="__main__":
    train_rmse = torch.Tensor([]).to(device)
    test_rmse = torch.Tensor([]).to(device)
    for epoch in range(args.epochs):
        train_rmse = torch.cat((train_rmse, train(epoch)), dim=0)
        test_rmse = torch.cat((test_rmse, test()), dim=0)
    plt.plot(range(args.epochs),train_rmse, range(args.epochs),test_rmse)
    plt.xlabel('epoch')
    plt.ylabel('RMSE')
    plt.xticks(range(0,args.epochs,2))
    plt.show()
```

### Result

![autoRec](https://user-images.githubusercontent.com/51329156/106747396-357d4980-6667-11eb-9b75-796767ce92de.png)

논문에서 제시한 결과만큼은 내려가지 않는 것 같습니다.. 그냥 한 번 구현해본 거에 대해 의의를 가져야 할 것 같아요.

### Conclusion

논문도 짧고 모델도 단순해서 구현이 익숙하시지 않은 분들도 쉽게 할 수 있을 것 같습니다. 코드 구현 시 궁금한 점 있으시면 메일 주세요. 감사합니다 !